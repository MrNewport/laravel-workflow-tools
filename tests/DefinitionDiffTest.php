<?php

declare(strict_types=1);

namespace MrNewport\LaravelWorkflowTools\Tests;

use MrNewport\LaravelWorkflow\Definition;
use MrNewport\LaravelWorkflowTools\DefinitionDiff;

final class DefinitionDiffTest extends TestCase
{
    public function test_identical_snapshots_have_no_changes(): void
    {
        $definition = Definition::fromArray($this->definitionData());
        $diff = (new DefinitionDiff)->between($definition, $definition);
        self::assertFalse($diff['changed']);
        self::assertSame([], $diff['changes']);
    }

    public function test_reports_version_assignment_and_transition_changes(): void
    {
        $before = $this->definitionData();
        $after = $before;
        $after['version'] = 2;
        $after['steps'][1]['assignment_strategy'] = 'single_user';
        $after['steps'][1]['assignment_params'] = ['user_id' => 17];
        $after['steps'][1]['transitions']['revise'] = ['END'];
        $diff = (new DefinitionDiff)->between(Definition::fromArray($before), Definition::fromArray($after));
        self::assertTrue($diff['changed']);
        $paths = array_column($diff['changes'], 'path');
        self::assertContains('/version', $paths);
        self::assertContains('/steps/1/assignment_strategy', $paths);
        self::assertContains('/steps/1/transitions/revise/0', $paths);
    }
}
