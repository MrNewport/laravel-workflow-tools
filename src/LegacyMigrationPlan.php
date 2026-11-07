<?php

declare(strict_types=1);

namespace MrNewport\LaravelWorkflowTools;

use InvalidArgumentException;
use MrNewport\LaravelWorkflow\Definition;

/** Reviews an explicit table export; it never executes stored callback names. */
final class LegacyMigrationPlan
{
    /**
     * @param  array<array-key, mixed>  $export
     * @return array{ready: bool, issues: list<array{path: string, reason: string}>, definition: array<string, mixed>|null}
     */
    public function review(array $export): array
    {
        $issues = [];
        foreach (array_diff(array_keys($export), ['name', 'version', 'initial', 'tasks', 'transitions']) as $key) {
            $issues[] = ['path' => '/'.$this->pointer($key), 'reason' => Messages::get('export_section')];
        }
        if (! isset($export['tasks'], $export['transitions']) || ! is_array($export['tasks']) || ! array_is_list($export['tasks']) || ! is_array($export['transitions']) || ! array_is_list($export['transitions'])) {
            throw new InvalidArgumentException(Messages::get('export_lists'));
        }

        $steps = [];
        $byId = [];
        $seen = [];
        foreach ($export['tasks'] as $index => $task) {
            if (! is_array($task) || ! isset($task['id']) || ! is_string($task['id'])) {
                $issues[] = ['path' => '/tasks/'.$index, 'reason' => Messages::get('task_id')];

                continue;
            }

            if (isset($seen[$task['id']])) {
                $issues[] = ['path' => '/tasks/'.$index.'/id', 'reason' => Messages::get('task_duplicate')];

                continue;
            }
            $seen[$task['id']] = true;
            if ($task['id'] === 'END') {
                // A terminal reference has no runtime task or assignment to migrate.
                $this->inspectExtraColumns($task, ['id', 'name'], '/tasks/'.$index, $issues);

                continue;
            }

            if ($task['id'] === 'CANCEL') {
                $issues[] = ['path' => '/tasks/'.$index, 'reason' => Messages::get('task_cancel')];

                continue;
            }

            $this->inspectExtraColumns($task, ['id', 'name'], '/tasks/'.$index, $issues);
            $byId[$task['id']] = count($steps);
            $steps[] = ['id' => $task['id'], 'name' => $task['name'] ?? $task['id'], 'transitions' => []];
        }

        foreach ($export['transitions'] as $index => $transition) {
            $path = '/transitions/'.$index;
            if (! is_array($transition)) {
                $issues[] = ['path' => $path, 'reason' => Messages::get('transition_object')];

                continue;
            }
            $this->inspectExtraColumns($transition, ['task', 'action', 'next_task'], $path, $issues);
            foreach (['task', 'action', 'next_task'] as $column) {
                if (! isset($transition[$column]) || ! is_string($transition[$column])) {
                    $issues[] = ['path' => $path.'/'.$column, 'reason' => Messages::get('transition_string')];

                    continue 2;
                }
            }
            if (! isset($byId[$transition['task']])) {
                $issues[] = ['path' => $path.'/task', 'reason' => Messages::get('transition_source')];

                continue;
            }
            if ($transition['next_task'] === 'CANCEL') {
                $issues[] = ['path' => $path.'/next_task', 'reason' => Messages::get('transition_cancel')];

                continue;
            }
            $steps[$byId[$transition['task']]]['transitions'][$transition['action']][] = $transition['next_task'];
        }

        if ($issues !== []) {
            return ['ready' => false, 'issues' => $issues, 'definition' => null];
        }

        try {
            $definition = Definition::fromArray([
                'name' => $export['name'] ?? null,
                'version' => $export['version'] ?? null,
                'initial' => $export['initial'] ?? null,
                'steps' => $steps,
            ]);
        } catch (InvalidArgumentException $exception) {
            return ['ready' => false, 'issues' => [['path' => '/', 'reason' => $exception->getMessage()]], 'definition' => null];
        }

        return ['ready' => true, 'issues' => [], 'definition' => $definition->toArray()];
    }

    /**
     * @param  array<array-key, mixed>  $row
     * @param  list<string>  $allowed
     * @param  list<array{path: string, reason: string}>  $issues
     */
    private function inspectExtraColumns(array $row, array $allowed, string $path, array &$issues): void
    {
        foreach ($row as $column => $value) {
            if (in_array($column, $allowed, true) || $value === null || $value === '') {
                continue;
            }
            if (in_array($column, ['email_notify', 'auto_load'], true) && in_array($value, [false, 0, '0'], true)) {
                continue;
            }
            $issues[] = ['path' => $path.'/'.$this->pointer($column), 'reason' => Messages::get('mapping_required')];
        }
    }

    private function pointer(string|int $value): string
    {
        return str_replace(['~', '/'], ['~0', '~1'], (string) $value);
    }
}
