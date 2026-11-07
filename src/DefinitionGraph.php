<?php

declare(strict_types=1);

namespace MrNewport\LaravelWorkflowTools;

use MrNewport\LaravelWorkflow\Definition;

final class DefinitionGraph
{
    public function mermaid(Definition $definition): string
    {
        $data = $definition->toArray();
        $edgeCount = 0;
        foreach ($data['steps'] as $step) {
            foreach ($step['transitions'] as $targets) {
                $edgeCount += count($targets);
            }
        }
        $title = $this->metadata(Messages::get('graph_title', ['name' => $data['name'], 'version' => $data['version']]));
        $description = $this->metadata(Messages::get('graph_description', ['steps' => count($data['steps']), 'edges' => $edgeCount, 'initial' => $data['initial']]));
        $chart = "---\nconfig:\n  securityLevel: strict\n  htmlLabels: false\n---\nflowchart TD\n    accTitle: ".$title."\n    accDescr: ".$description."\n    entry((\"".$this->label(Messages::get('graph_start'))."\"))\n";
        $nodes = [];
        foreach ($data['steps'] as $index => $step) {
            $nodes[$step['id']] = 's'.$index;
            $chart .= '    s'.$index.'["'.$this->label($step['name'] ?? $step['id']).'"]'."\n";
        }

        $chart .= "    terminal((\"END\"))\n    entry --> ".$nodes[$data['initial']]."\n";
        $labels = [];
        foreach ($data['steps'] as $step) {
            foreach ($step['transitions'] as $action => $targets) {
                $label = $labels[$action] ??= $this->label($action);
                foreach ($targets as $target) {
                    $chart .= '    '.$nodes[$step['id']].' -->|"'.$label.'"| '.($target === 'END' ? 'terminal' : $nodes[$target])."\n";
                }
            }
        }

        return $chart;
    }

    /** Equivalent plain-text transitions for readers that do not render diagrams. */
    public function text(Definition $definition): string
    {
        $data = $definition->toArray();
        $lines = [Messages::get('graph_title', ['name' => $data['name'], 'version' => $data['version']]), Messages::get('graph_initial', ['initial' => $data['initial']])];
        foreach ($data['steps'] as $step) {
            $lines[] = ($step['name'] ?? $step['id']).' ('.$step['id'].')';
            foreach ($step['transitions'] as $action => $targets) {
                $lines[] = '  '.$action.' -> '.implode(', ', $targets);
            }
        }

        return implode("\n", $lines)."\n";
    }

    private function metadata(string $value): string
    {
        // Single-line plain text cannot introduce Mermaid directives or markup.
        return preg_replace('/[^\p{L}\p{M}\p{N} ._:-]/u', ' ', $value) ?? '';
    }

    private function label(string $value): string
    {
        // Labels cannot inject node IDs, directives, links, Markdown or HTML.
        $value = preg_replace('/[\r\n\t]+/u', ' ', $value) ?? '';

        return preg_replace_callback('/[^\p{L}\p{N} ._-]/u', fn (array $match): string => '#'.mb_ord($match[0], 'UTF-8').';', $value) ?? '';
    }
}
