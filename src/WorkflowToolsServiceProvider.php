<?php

declare(strict_types=1);

namespace MrNewport\LaravelWorkflowTools;

use Illuminate\Support\ServiceProvider;
use MrNewport\LaravelWorkflowTools\Commands\DiffDefinitionCommand;
use MrNewport\LaravelWorkflowTools\Commands\ExportDefinitionCommand;
use MrNewport\LaravelWorkflowTools\Commands\GraphDefinitionCommand;
use MrNewport\LaravelWorkflowTools\Commands\LegacyPlanCommand;
use MrNewport\LaravelWorkflowTools\Commands\PublishDefinitionCommand;
use MrNewport\LaravelWorkflowTools\Commands\ValidateDefinitionCommand;
use MrNewport\LaravelWorkflowTools\Contracts\FailureReporter;
use MrNewport\LaravelWorkflowTools\Diagnostics\LaravelFailureReporter;

final class WorkflowToolsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bindIf(FailureReporter::class, LaravelFailureReporter::class);
    }

    public function boot(): void
    {
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'laravel-workflow-tools');
        if ($this->app->runningInConsole()) {
            $this->publishes([__DIR__.'/../lang' => lang_path('vendor/laravel-workflow-tools')], 'workflow-tools-translations');
            $this->commands([
                ValidateDefinitionCommand::class,
                ExportDefinitionCommand::class,
                GraphDefinitionCommand::class,
                DiffDefinitionCommand::class,
                PublishDefinitionCommand::class,
                LegacyPlanCommand::class,
            ]);
        }
    }
}
