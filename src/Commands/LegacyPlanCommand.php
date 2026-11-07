<?php

declare(strict_types=1);

namespace MrNewport\LaravelWorkflowTools\Commands;

use MrNewport\LaravelWorkflowTools\DefinitionFiles;
use MrNewport\LaravelWorkflowTools\LegacyMigrationPlan;

final class LegacyPlanCommand extends DefinitionCommand
{
    protected $signature = 'workflow:legacy-plan {file : Local JSON export of task references and transitions}';

    protected $description = 'Review legacy definition conversion blockers without executing callbacks or migrating records';

    public function handle(DefinitionFiles $files, LegacyMigrationPlan $planner): int
    {
        return $this->executeSafely(function () use ($files, $planner): int {
            $plan = $planner->review($files->readArray((string) $this->argument('file')));
            $this->writeJson($plan);

            return $plan['ready'] ? self::SUCCESS : self::FAILURE;
        });
    }
}
