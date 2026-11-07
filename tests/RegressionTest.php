<?php

declare(strict_types=1);

namespace MrNewport\LaravelWorkflowTools\Tests;

use Illuminate\Console\OutputStyle;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use MrNewport\LaravelWorkflow\Definition;
use MrNewport\LaravelWorkflow\Models\PublishedDefinition;
use MrNewport\LaravelWorkflowTools\Commands\DefinitionCommand;
use MrNewport\LaravelWorkflowTools\Contracts\FailureReporter;
use MrNewport\LaravelWorkflowTools\DefinitionFiles;
use MrNewport\LaravelWorkflowTools\DefinitionGraph;
use MrNewport\LaravelWorkflowTools\Diagnostics\LaravelFailureReporter;
use MrNewport\LaravelWorkflowTools\WorkflowToolsServiceProvider;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;
use TypeError;

final class RegressionTest extends TestCase
{
    public function test_provider_preserves_an_existing_host_failure_reporter(): void
    {
        $reporter = $this->captureFailures();
        (new WorkflowToolsServiceProvider($this->app))->register();
        self::assertSame($reporter, $this->app->make(FailureReporter::class));
    }

    public function test_after_commit_database_failure_reports_uncertain_state_and_retains_diagnostics(): void
    {
        $reporter = $this->captureFailures();
        $data = $this->definitionData();
        $fingerprint = Definition::fromArray($data)->fingerprint();
        PublishedDefinition::created(function (): void {
            DB::afterCommit(fn () => DB::table('missing_protected_delivery_table')->insert(['secret' => 'protected-binding']));
        });

        self::assertSame(1, Artisan::call('workflow:publish', ['file' => $this->jsonFile($data), '--apply' => true, '--expect' => $fingerprint]));
        $printed = Artisan::output();
        self::assertTrue(PublishedDefinition::query()->whereKey($fingerprint)->exists());
        self::assertStringContainsString('Check the published state', $printed);
        self::assertStringNotContainsString('No definition change was committed', $printed);
        self::assertStringNotContainsString('protected-binding', $printed);
        self::assertStringNotContainsString('missing_protected_delivery_table', $printed);
        self::assertCount(1, $reporter->failures);
        self::assertStringContainsString($reporter->failures[0]['reference'], $printed);
    }

    public function test_database_failure_before_commit_rolls_back_without_an_inaccurate_assurance(): void
    {
        $this->captureFailures();
        $data = $this->definitionData();
        PublishedDefinition::created(fn () => DB::table('missing_delivery_table')->insert(['x' => 1]));
        self::assertSame(1, Artisan::call('workflow:publish', ['file' => $this->jsonFile($data), '--apply' => true, '--expect' => Definition::fromArray($data)->fingerprint()]));
        self::assertSame(0, PublishedDefinition::query()->count());
        self::assertStringContainsString('Check the published state', Artisan::output());
    }

    public function test_unexpected_errors_are_reported_with_a_reference_and_never_dumped_at_debug_verbosity(): void
    {
        $reporter = $this->captureFailures();
        $command = new class extends DefinitionCommand
        {
            public function probe(): int
            {
                return $this->executeSafely(fn () => throw new TypeError('private-diagnostic-cause'));
            }
        };
        $output = new BufferedOutput(OutputInterface::VERBOSITY_DEBUG);
        $command->setOutput(new OutputStyle(new ArrayInput([]), $output));
        self::assertSame(1, $command->probe());
        $printed = $output->fetch();
        self::assertCount(1, $reporter->failures);
        self::assertSame('private-diagnostic-cause', $reporter->failures[0]['exception']->getMessage());
        self::assertStringContainsString($reporter->failures[0]['reference'], $printed);
        self::assertStringNotContainsString('private-diagnostic-cause', $printed);
    }

    public function test_default_reporter_preserves_the_exception_chain_for_the_host_handler(): void
    {
        $exception = new TypeError('protected cause');
        $handler = $this->createMock(ExceptionHandler::class);
        $handler->expects(self::once())->method('report')->with(self::callback(fn (Throwable $reported): bool => $reported->getPrevious() === $exception && str_contains($reported->getMessage(), 'abc123')));
        (new LaravelFailureReporter($handler))->report($exception, 'abc123');
    }

    public function test_a_failing_host_reporter_does_not_mask_the_original_command_failure(): void
    {
        $this->app->instance(FailureReporter::class, new class implements FailureReporter
        {
            public function report(Throwable $exception, string $reference): void
            {
                throw new \RuntimeException('private reporter failure');
            }
        });
        $command = new class extends DefinitionCommand
        {
            public function probe(): int
            {
                return $this->executeSafely(fn () => throw new TypeError('private operation failure'));
            }
        };
        $output = new BufferedOutput;
        $command->setOutput(new OutputStyle(new ArrayInput([]), $output));
        self::assertSame(1, $command->probe());
        $printed = $output->fetch();
        self::assertStringContainsString('The definition operation failed.', $printed);
        self::assertStringNotContainsString('private reporter failure', $printed);
        self::assertStringNotContainsString('private operation failure', $printed);
    }

    public function test_oversized_saved_definition_export_fails_explicitly_without_printing_a_partial_snapshot(): void
    {
        $data = $this->denseDefinition();
        foreach ($data['steps'] as &$step) {
            for ($action = 10; $action < 30; $action++) {
                $step['transitions']['route'.str_pad((string) $action, 45, 'x')] = array_values($step['transitions'])[1];
            }
        }
        unset($step);
        $definition = Definition::fromStoredArray($data);
        self::assertGreaterThan(DefinitionFiles::MAX_BYTES, strlen(json_encode($definition->toArray(), JSON_THROW_ON_ERROR)));
        $snapshot = $definition->toArray();
        PublishedDefinition::query()->create(['id' => $definition->fingerprint(), 'name' => $snapshot['name'], 'version' => $snapshot['version'], 'snapshot' => $snapshot]);
        self::assertSame(1, Artisan::call('workflow:export', ['fingerprint' => $definition->fingerprint()]));
        $printed = Artisan::output();
        self::assertStringContainsString('normalized definition exceeds', $printed);
        self::assertStringNotContainsString('"steps"', $printed);
        self::assertSame(1, PublishedDefinition::query()->count());
    }

    public function test_large_accepted_definition_service_and_command_exports_can_be_read_again(): void
    {
        $data = $this->denseDefinition();
        $files = new DefinitionFiles;
        $definition = $files->read($this->jsonFile($data));
        self::assertGreaterThan(DefinitionFiles::MAX_BYTES, strlen(json_encode($definition->toArray(), JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)));
        $encoded = $files->encode($definition);
        self::assertLessThanOrEqual(DefinitionFiles::MAX_BYTES, strlen($encoded));
        self::assertSame($definition->fingerprint(), $files->read($this->jsonFile($encoded))->fingerprint());

        self::assertSame(0, Artisan::call('workflow:publish', ['file' => $this->jsonFile($data), '--apply' => true, '--expect' => $definition->fingerprint()]));
        self::assertSame(0, Artisan::call('workflow:export', ['fingerprint' => $definition->fingerprint()]));
        $output = Artisan::output();
        self::assertLessThanOrEqual(DefinitionFiles::MAX_BYTES, strlen($output));
        self::assertSame($definition->fingerprint(), $files->read($this->jsonFile($output))->fingerprint());
    }

    public function test_graph_exports_have_metadata_and_a_complete_text_alternative(): void
    {
        $definition = Definition::fromArray($this->definitionData());
        $graph = new DefinitionGraph;
        self::assertStringContainsString('accTitle: document-review version 1', $graph->mermaid($definition));
        self::assertStringContainsString('accDescr: Workflow with 2 steps and 3 transitions.', $graph->mermaid($definition));
        $text = $graph->text($definition);
        self::assertStringContainsString('Initial step: draft', $text);
        self::assertStringContainsString('approve -> END', $text);
        self::assertStringContainsString('revise -> draft', $text);
        self::assertSame(0, Artisan::call('workflow:graph', ['file' => $this->jsonFile($this->definitionData()), '--format' => 'text']));
        self::assertSame($text, Artisan::output());
        self::assertSame(1, Artisan::call('workflow:graph', ['file' => $this->jsonFile($this->definitionData()), '--format' => 'html']));
    }

    public function test_translated_graph_metadata_is_single_line_and_cannot_inject_directives(): void
    {
        $this->app['translator']->addLines(['messages.graph_title' => "Safe\nclick s0 href <script>bad</script>{}"], 'fr', 'laravel-workflow-tools');
        $this->app->setLocale('fr');
        $chart = (new DefinitionGraph)->mermaid(Definition::fromArray($this->definitionData()));
        self::assertStringNotContainsString("\nclick ", $chart);
        self::assertStringNotContainsString('<script>', $chart);
        self::assertSame(1, substr_count($chart, 'flowchart TD'));
    }

    public function test_accessible_metadata_preserves_combining_characters_in_translated_titles(): void
    {
        $title = "समीक्षा Cafe\u{0301}";
        $this->app['translator']->addLines(['messages.graph_title' => $title], 'hi', 'laravel-workflow-tools');
        $this->app->setLocale('hi');
        $chart = (new DefinitionGraph)->mermaid(Definition::fromArray($this->definitionData()));
        self::assertStringContainsString('accTitle: '.$title, $chart);
    }

    public function test_host_translations_apply_to_diagnostics_and_command_help(): void
    {
        $this->app['translator']->addLines(['messages.file_path' => 'Utilisez un fichier JSON local.', 'messages.commands.workflow:graph' => 'Afficher le diagramme.'], 'fr', 'laravel-workflow-tools');
        $this->app->setLocale('fr');
        self::assertSame(1, Artisan::call('workflow:validate', ['file' => 'https://example.com/private']));
        self::assertStringContainsString('Utilisez un fichier JSON local.', Artisan::output());
        self::assertSame('Afficher le diagramme.', Artisan::all()['workflow:graph']->getDescription());
    }

    private function captureFailures(): FailureReporter
    {
        $reporter = new class implements FailureReporter
        {
            public array $failures = [];

            public function report(Throwable $exception, string $reference): void
            {
                $this->failures[] = compact('exception', 'reference');
            }
        };
        $this->app->instance(FailureReporter::class, $reporter);

        return $reporter;
    }

    private function denseDefinition(): array
    {
        $ids = [];
        for ($i = 0; $i < 240; $i++) {
            $ids[] = 'step'.str_pad((string) $i, 60, '0', STR_PAD_LEFT);
        }
        $steps = [];
        foreach ($ids as $index => $id) {
            $targets = [];
            for ($offset = 1; $offset <= 4; $offset++) {
                $targets[] = $ids[($index + $offset) % count($ids)];
            }
            $transitions = ['finish' => ['END']];
            for ($action = 0; $action < 10; $action++) {
                $transitions['route'.str_pad((string) $action, 45, 'x')] = $targets;
            }
            $steps[] = ['id' => $id, 'name' => str_repeat('A', 255), 'transitions' => $transitions];
        }

        return ['name' => 'dense-review', 'version' => 1, 'initial' => $ids[0], 'steps' => $steps];
    }
}
