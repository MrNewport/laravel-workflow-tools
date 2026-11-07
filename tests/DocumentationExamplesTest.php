<?php

declare(strict_types=1);

namespace MrNewport\LaravelWorkflowTools\Tests;

use Illuminate\Support\Facades\Artisan;
use MrNewport\LaravelWorkflowTools\DefinitionDiff;
use MrNewport\LaravelWorkflowTools\DefinitionFiles;
use MrNewport\LaravelWorkflowTools\DefinitionGraph;
use MrNewport\LaravelWorkflowTools\LegacyMigrationPlan;

final class DocumentationExamplesTest extends TestCase
{
    public function test_shipped_examples_follow_the_documented_review_publish_export_round_trip(): void
    {
        $files = new DefinitionFiles;
        foreach (['review', 'procurement'] as $example) {
            $path = __DIR__.'/../examples/'.$example.'.json';
            $definition = $files->read($path);
            $this->assertStringContainsString('accTitle:', (new DefinitionGraph)->mermaid($definition));
            $this->assertStringContainsString('Initial step:', (new DefinitionGraph)->text($definition));
            $this->assertSame(0, Artisan::call('workflow:validate', ['file' => $path]));
            $this->assertSame($definition->fingerprint(), json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR)['fingerprint']);
            $this->assertSame(0, Artisan::call('workflow:publish', ['file' => $path, '--apply' => true, '--expect' => $definition->fingerprint()]));
            $this->assertSame(0, Artisan::call('workflow:export', ['fingerprint' => $definition->fingerprint()]));
            $export = $this->jsonFile(Artisan::output());
            $this->assertFalse((new DefinitionDiff)->between($definition, $files->read($export))['changed']);
            $this->assertSame(0, Artisan::call('workflow:diff', ['before' => $path, 'after' => $export, '--check' => true]));
        }
        $plan = (new LegacyMigrationPlan)->review($files->readArray(__DIR__.'/../examples/legacy-review.json'));
        $this->assertTrue($plan['ready']);
        $this->assertFalse((new DefinitionDiff)->between($files->read(__DIR__.'/../examples/review.json'), $files->read($this->jsonFile($plan['definition'])))['changed']);
    }
}
