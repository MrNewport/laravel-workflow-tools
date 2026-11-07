<?php

declare(strict_types=1);

namespace MrNewport\LaravelWorkflowTools\Commands;

use MrNewport\LaravelWorkflowTools\DefinitionFiles;

final class ValidateDefinitionCommand extends DefinitionCommand
{
    protected $signature = 'workflow:validate {file : Local JSON definition}';

    protected $description = 'Validate a workflow definition and print its canonical fingerprint';

    public function handle(DefinitionFiles $files): int
    {
        return $this->executeSafely(function () use ($files): int {
            $definition = $files->read((string) $this->argument('file'));
            $this->writeJson(['valid' => true, 'name' => $definition->name, 'version' => $definition->version, 'fingerprint' => $definition->fingerprint()]);

            return self::SUCCESS;
        });
    }
}
