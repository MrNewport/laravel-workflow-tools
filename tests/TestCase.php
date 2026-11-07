<?php

declare(strict_types=1);

namespace MrNewport\LaravelWorkflowTools\Tests;

use MrNewport\LaravelFlow\Providers\FlowServiceProvider;
use MrNewport\LaravelWorkflow\WorkflowServiceProvider;
use MrNewport\LaravelWorkflowTools\WorkflowToolsServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    private array $temporaryFiles = [];

    protected function getPackageProviders($app): array
    {
        return [FlowServiceProvider::class, WorkflowServiceProvider::class, WorkflowToolsServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        if (getenv('WORKFLOW_TOOLS_MYSQL_DATABASE') === 'workflow_tools_test') {
            $app['config']->set('database.default', 'mysql');
            $app['config']->set('database.connections.mysql', [
                'driver' => 'mysql', 'host' => getenv('MYSQL_TEST_HOST') ?: '127.0.0.1',
                'port' => getenv('MYSQL_TEST_PORT') ?: 3306,
                'unix_socket' => getenv('MYSQL_TEST_SOCKET') ?: '',
                'database' => 'workflow_tools_test', 'username' => getenv('MYSQL_TEST_USERNAME') ?: 'root',
                'password' => getenv('MYSQL_TEST_PASSWORD') ?: '',
                'charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci', 'prefix' => '', 'strict' => true,
            ]);

            return;
        }
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true]);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan(getenv('WORKFLOW_TOOLS_MYSQL_DATABASE') === 'workflow_tools_test' ? 'migrate:fresh' : 'migrate', ['--force' => true])->run();
    }

    protected function tearDown(): void
    {
        foreach ($this->temporaryFiles as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        parent::tearDown();
    }

    protected function definitionData(): array
    {
        return [
            'name' => 'document-review', 'version' => 1, 'initial' => 'draft',
            'steps' => [
                ['id' => 'draft', 'name' => 'Draft', 'transitions' => ['submit' => ['review']]],
                ['id' => 'review', 'name' => 'Review', 'transitions' => ['approve' => ['END'], 'revise' => ['draft']]],
            ],
        ];
    }

    protected function jsonFile(array|string $contents): string
    {
        $path = tempnam(sys_get_temp_dir(), 'workflow-tools-');
        file_put_contents($path, is_array($contents) ? json_encode($contents, JSON_THROW_ON_ERROR) : $contents);
        $this->temporaryFiles[] = $path;

        return $path;
    }
}
