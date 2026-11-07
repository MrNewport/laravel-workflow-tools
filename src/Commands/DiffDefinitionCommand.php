<?php

declare(strict_types=1);

namespace MrNewport\LaravelWorkflowTools\Commands;

use MrNewport\LaravelWorkflowTools\DefinitionDiff;
use MrNewport\LaravelWorkflowTools\DefinitionFiles;

final class DiffDefinitionCommand extends DefinitionCommand
{
    protected $signature = 'workflow:diff {before : Local JSON definition} {after : Local JSON definition} {--check : Exit with status 1 if definitions differ}';

    protected $description = 'Compare validated definition snapshots without changing the database';

    public function handle(DefinitionFiles $files, DefinitionDiff $diff): int
    {
        return $this->executeSafely(function () use ($files, $diff): int {
            $result = $diff->between($files->read((string) $this->argument('before')), $files->read((string) $this->argument('after')));
            $this->writeJson($result);

            return $this->option('check') && $result['changed'] ? self::FAILURE : self::SUCCESS;
        });
    }
}
