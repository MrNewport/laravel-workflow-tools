<?php

declare(strict_types=1);

namespace MrNewport\LaravelWorkflowTools\Tests;

use MrNewport\LaravelWorkflowTools\LegacyMigrationPlan;

final class LegacyMigrationPlanTest extends TestCase
{
    private function exportData(): array
    {
        return ['name' => 'review', 'version' => 1, 'initial' => 'review', 'tasks' => [
            ['id' => 'review', 'name' => 'Review', 'proc_func' => null, 'email_notify' => 0],
            ['id' => 'END', 'name' => 'Complete', 'email_notify' => 0],
        ], 'transitions' => [['task' => 'review', 'action' => 'approve', 'next_task' => 'END']]];
    }

    public function test_converts_only_explicitly_inert_definition_rows(): void
    {
        $plan = (new LegacyMigrationPlan)->review($this->exportData());
        self::assertTrue($plan['ready']);
        self::assertSame([], $plan['issues']);
        self::assertSame(['approve' => ['END']], $plan['definition']['steps'][0]['transitions']);
    }

    public function test_blocks_hooks_assignments_notifications_and_status_updates(): void
    {
        $data = $this->exportData();
        $data['tasks'][0] += ['role_logic' => 'entity_role', 'org_logic' => 'entity_org', 'preproc_func' => 'canAdvance'];
        $data['tasks'][0]['email_notify'] = 1;
        $data['transitions'][0]['status_column'] = 'status';
        $plan = (new LegacyMigrationPlan)->review($data);
        self::assertFalse($plan['ready']);
        self::assertNull($plan['definition']);
        self::assertCount(5, $plan['issues']);
    }

    public function test_never_silently_maps_cancellation_to_completion(): void
    {
        $data = $this->exportData();
        $data['transitions'][0]['next_task'] = 'CANCEL';
        $plan = (new LegacyMigrationPlan)->review($data);
        self::assertFalse($plan['ready']);
        self::assertStringContainsString('Cancellation', $plan['issues'][0]['reason']);
    }

    public function test_blocks_orphans_and_preserves_source_data(): void
    {
        $data = $this->exportData();
        $data['transitions'][0]['next_task'] = 'missing';
        $copy = $data;
        self::assertFalse((new LegacyMigrationPlan)->review($data)['ready']);
        self::assertSame($copy, $data);
    }

    public function test_unknown_history_sections_cannot_be_dropped(): void
    {
        $data = $this->exportData();
        $data['history'] = [['id' => 9]];
        $plan = (new LegacyMigrationPlan)->review($data);
        self::assertFalse($plan['ready']);
        self::assertSame('/history', $plan['issues'][0]['path']);
    }

    public function test_zero_and_false_metadata_require_an_explicit_mapping(): void
    {
        foreach (['position' => 0, 'enabled' => false, 'role' => '0'] as $key => $value) {
            $data = $this->exportData();
            $data['tasks'][0][$key] = $value;
            $plan = (new LegacyMigrationPlan)->review($data);
            self::assertFalse($plan['ready']);
            self::assertSame('/tasks/0/'.$key, $plan['issues'][0]['path']);
        }
    }

    public function test_reserved_task_ids_must_also_be_unique(): void
    {
        $data = $this->exportData();
        $data['tasks'][] = ['id' => 'END', 'name' => 'Complete'];
        $plan = (new LegacyMigrationPlan)->review($data);
        self::assertFalse($plan['ready']);
        self::assertSame('/tasks/2/id', $plan['issues'][0]['path']);
    }

    public function test_issue_paths_escape_json_pointer_characters(): void
    {
        $data = $this->exportData();
        $data['tasks'][0]['custom/flag~'] = false;
        $data['custom/section~'] = [];
        $paths = array_column((new LegacyMigrationPlan)->review($data)['issues'], 'path');
        self::assertContains('/custom~1section~0', $paths);
        self::assertContains('/tasks/0/custom~1flag~0', $paths);
    }
}
