<?php

declare(strict_types=1);

namespace MrNewport\LaravelWorkflowTools\Commands;

use MrNewport\LaravelWorkflow\DefinitionPublisher;
use MrNewport\LaravelWorkflowTools\DefinitionFiles;
use MrNewport\LaravelWorkflowTools\Messages;

final class PublishDefinitionCommand extends DefinitionCommand
{
    protected $signature = 'workflow:publish {file : Local JSON definition} {--apply : Persist this reviewed definition} {--expect= : Expected full canonical SHA-256 fingerprint}';

    protected $description = 'Preview or publish an immutable definition through Laravel Workflow';

    public function handle(DefinitionFiles $files, DefinitionPublisher $publisher): int
    {
        return $this->executeSafely(function () use ($files, $publisher): int {
            $definition = $files->read((string) $this->argument('file'));
            $fingerprint = $definition->fingerprint();
            $expected = $this->option('expect');
            if ($expected !== null && ! hash_equals($fingerprint, (string) $expected)) {
                throw new \InvalidArgumentException(Messages::get('fingerprint_changed'));
            }
            if ($this->option('apply') && $expected === null) {
                throw new \InvalidArgumentException(Messages::get('publish_review'));
            }
            if ($this->option('apply')) {
                $publisher->publish($definition);
            }
            $this->writeJson(['published' => (bool) $this->option('apply'), 'name' => $definition->name, 'version' => $definition->version, 'fingerprint' => $fingerprint]);

            return self::SUCCESS;
        });
    }
}
