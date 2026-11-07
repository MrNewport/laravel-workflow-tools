<?php

declare(strict_types=1);

namespace MrNewport\LaravelWorkflowTools;

use MrNewport\LaravelWorkflow\Definition;

final class DefinitionDiff
{
    /** @return array{changed: bool, before: string, after: string, changes: list<array{path: string, before: mixed, after: mixed}>} */
    public function between(Definition $before, Definition $after): array
    {
        $beforeHash = $before->fingerprint();
        $afterHash = $after->fingerprint();
        $changes = [];
        if ($beforeHash !== $afterHash) {
            $this->compare($before->toArray(), $after->toArray(), '', $changes);
        }

        return [
            'changed' => $beforeHash !== $afterHash,
            'before' => $beforeHash,
            'after' => $afterHash,
            'changes' => $changes,
        ];
    }

    /** @param list<array{path: string, before: mixed, after: mixed}> $changes */
    private function compare(mixed $before, mixed $after, string $path, array &$changes): void
    {
        if ($before === $after) {
            return;
        }

        if (! is_array($before) || ! is_array($after)) {
            $changes[] = ['path' => $path, 'before' => $before, 'after' => $after];

            return;
        }

        $keys = array_unique([...array_keys($before), ...array_keys($after)]);
        foreach ($keys as $key) {
            $child = $path.'/'.str_replace(['~', '/'], ['~0', '~1'], (string) $key);
            if (! array_key_exists($key, $before) || ! array_key_exists($key, $after)) {
                $changes[] = ['path' => $child, 'before' => $before[$key] ?? null, 'after' => $after[$key] ?? null];
            } else {
                $this->compare($before[$key], $after[$key], $child, $changes);
            }
        }
    }
}
