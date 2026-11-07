<?php

declare(strict_types=1);

namespace MrNewport\LaravelWorkflowTools\Tests;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use MrNewport\LaravelFlow\Models\FlowStep;
use MrNewport\LaravelWorkflow\Definition;
use MrNewport\LaravelWorkflow\Models\PublishedDefinition;

final class CommandsTest extends TestCase
{
    public function test_validation_graph_and_diff_are_read_only(): void
    {
        $file = $this->jsonFile($this->definitionData());
        $stepsBefore = FlowStep::query()->count();
        self::assertSame(0, Artisan::call('workflow:validate', ['file' => $file]));
        self::assertTrue(json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR)['valid']);
        self::assertSame(0, Artisan::call('workflow:graph', ['file' => $file]));
        self::assertStringContainsString('flowchart TD', Artisan::output());
        self::assertSame(0, Artisan::call('workflow:diff', ['before' => $file, 'after' => $file, '--check' => true]));
        self::assertFalse(json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR)['changed']);
        self::assertSame(0, PublishedDefinition::query()->count());
        self::assertSame($stepsBefore, FlowStep::query()->count());
    }

    public function test_publish_preview_requires_apply_and_reviewed_fingerprint_to_mutate(): void
    {
        $file = $this->jsonFile($this->definitionData());
        $fingerprint = Definition::fromArray($this->definitionData())->fingerprint();
        self::assertSame(0, Artisan::call('workflow:publish', ['file' => $file]));
        self::assertFalse(json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR)['published']);
        self::assertSame(0, PublishedDefinition::query()->count());
        self::assertSame(1, Artisan::call('workflow:publish', ['file' => $file, '--apply' => true]));
        self::assertSame(1, Artisan::call('workflow:publish', ['file' => $file, '--apply' => true, '--expect' => str_repeat('0', 64)]));
        self::assertSame(0, PublishedDefinition::query()->count());
        self::assertSame(0, Artisan::call('workflow:publish', ['file' => $file, '--apply' => true, '--expect' => $fingerprint]));
        self::assertTrue(json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR)['published']);
        self::assertSame(1, PublishedDefinition::query()->count());
        self::assertGreaterThan(1, FlowStep::query()->count());
        self::assertSame(0, Artisan::call('workflow:publish', ['file' => $file, '--apply' => true, '--expect' => $fingerprint]));
        self::assertSame(1, PublishedDefinition::query()->count());

        self::assertSame(0, Artisan::call('workflow:export', ['fingerprint' => $fingerprint]));
        self::assertSame(Definition::fromArray($this->definitionData())->toArray(), json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR));
    }

    public function test_changed_same_version_is_not_overwritten(): void
    {
        $before = $this->definitionData();
        $after = $before;
        $after['steps'][0]['name'] = 'Edited draft';
        foreach ([$before, $after] as $index => $data) {
            $result = Artisan::call('workflow:publish', ['file' => $this->jsonFile($data), '--apply' => true, '--expect' => Definition::fromArray($data)->fingerprint()]);
            self::assertSame($index === 0 ? 0 : 1, $result);
        }
        self::assertSame(1, PublishedDefinition::query()->count());
        self::assertSame(Definition::fromArray($before)->fingerprint(), PublishedDefinition::query()->sole()->getKey());
    }

    public function test_export_rejects_a_tampered_persisted_snapshot(): void
    {
        $data = $this->definitionData();
        $fingerprint = Definition::fromArray($data)->fingerprint();
        self::assertSame(0, Artisan::call('workflow:publish', ['file' => $this->jsonFile($data), '--apply' => true, '--expect' => $fingerprint]));
        $data['steps'][0]['name'] = 'Tampered';
        DB::table('workflow_definitions')->where('id', $fingerprint)->update(['snapshot' => json_encode($data, JSON_THROW_ON_ERROR)]);
        self::assertSame(1, Artisan::call('workflow:export', ['fingerprint' => $fingerprint]));
        self::assertStringNotContainsString('"steps"', Artisan::output());
    }

    public function test_diff_check_signals_change_without_publishing(): void
    {
        $after = $this->definitionData();
        $after['version'] = 2;
        self::assertSame(1, Artisan::call('workflow:diff', ['before' => $this->jsonFile($this->definitionData()), 'after' => $this->jsonFile($after), '--check' => true]));
        self::assertTrue(json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR)['changed']);
        self::assertSame(0, PublishedDefinition::query()->count());
    }

    public function test_invalid_input_fails_before_mutation(): void
    {
        self::assertSame(1, Artisan::call('workflow:validate', ['file' => $this->jsonFile('invalid')]));
        self::assertSame(1, Artisan::call('workflow:export', ['fingerprint' => '../bad']));
        self::assertSame(1, Artisan::call('workflow:export', ['fingerprint' => str_repeat('a', 64)]));
        self::assertSame(0, PublishedDefinition::query()->count());
    }

    public function test_legacy_plan_reports_blockers_and_never_creates_records(): void
    {
        $file = $this->jsonFile(['name' => 'review', 'version' => 1, 'initial' => 'review', 'tasks' => [['id' => 'review', 'preproc_func' => 'sensitiveHook']], 'transitions' => []]);
        self::assertSame(1, Artisan::call('workflow:legacy-plan', ['file' => $file]));
        $report = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);
        self::assertFalse($report['ready']);
        self::assertSame('/tasks/0/preproc_func', $report['issues'][0]['path']);
        self::assertStringNotContainsString('sensitiveHook', Artisan::output());
        self::assertSame(0, PublishedDefinition::query()->count());
    }
}
