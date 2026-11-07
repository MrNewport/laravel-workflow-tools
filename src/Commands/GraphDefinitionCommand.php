<?php

declare(strict_types=1);

namespace MrNewport\LaravelWorkflowTools\Commands;

use MrNewport\LaravelWorkflowTools\DefinitionFiles;
use MrNewport\LaravelWorkflowTools\DefinitionGraph;
use MrNewport\LaravelWorkflowTools\Messages;

final class GraphDefinitionCommand extends DefinitionCommand
{
    protected $signature = 'workflow:graph {file : Local JSON definition} {--format=mermaid : mermaid or text}';

    protected $description = 'Print a local Mermaid diagram without sending data to another service';

    public function handle(DefinitionFiles $files, DefinitionGraph $graph): int
    {
        return $this->executeSafely(function () use ($files, $graph): int {
            $format = $this->option('format');
            if (! in_array($format, ['mermaid', 'text'], true)) {
                throw new \InvalidArgumentException(Messages::get('graph_format'));
            }
            $definition = $files->read((string) $this->argument('file'));
            $this->writeRaw($format === 'text' ? $graph->text($definition) : $graph->mermaid($definition));

            return self::SUCCESS;
        });
    }
}
