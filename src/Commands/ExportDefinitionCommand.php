<?php

declare(strict_types=1);

namespace MrNewport\LaravelWorkflowTools\Commands;

use MrNewport\LaravelWorkflow\Models\PublishedDefinition;
use MrNewport\LaravelWorkflowTools\DefinitionFiles;
use MrNewport\LaravelWorkflowTools\Messages;

final class ExportDefinitionCommand extends DefinitionCommand
{
    protected $signature = 'workflow:export {fingerprint : Published definition SHA-256 fingerprint}';

    protected $description = 'Print a published immutable definition snapshot as JSON';

    public function handle(DefinitionFiles $files): int
    {
        return $this->executeSafely(function () use ($files): int {
            $fingerprint = (string) $this->argument('fingerprint');
            if (! preg_match('/\A[a-f0-9]{64}\z/D', $fingerprint)) {
                throw new \InvalidArgumentException(Messages::get('fingerprint_format'));
            }
            $published = PublishedDefinition::query()->find($fingerprint);
            if ($published === null) {
                throw new \InvalidArgumentException(Messages::get('definition_missing'));
            }
            $this->writeRaw($files->encode($published->definition()));

            return self::SUCCESS;
        });
    }
}
