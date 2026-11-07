<?php

declare(strict_types=1);

namespace MrNewport\LaravelWorkflowTools\Tests;

use MrNewport\LaravelWorkflow\Definition;
use MrNewport\LaravelWorkflowTools\DefinitionGraph;

final class DefinitionGraphTest extends TestCase
{
    public function test_preserves_cycles_branches_and_terminal_edges_once(): void
    {
        $data = $this->definitionData();
        $data['steps'][0]['transitions']['submit'] = ['review', 'legal'];
        $data['steps'][] = ['id' => 'legal', 'name' => 'Legal', 'transitions' => ['approve' => ['END']]];
        $chart = (new DefinitionGraph)->mermaid(Definition::fromArray($data));
        self::assertStringContainsString('securityLevel: strict', $chart);
        self::assertStringContainsString('htmlLabels: false', $chart);
        self::assertSame(5, substr_count($chart, '-->|'));
        self::assertSame(1, substr_count($chart, '["Draft"]'));
        self::assertSame(2, substr_count($chart, '| terminal'));
        self::assertSame($chart, (new DefinitionGraph)->mermaid(Definition::fromArray($data)));
    }

    public function test_untrusted_labels_cannot_add_html_or_mermaid_syntax(): void
    {
        $data = $this->definitionData();
        $data['steps'][0]['name'] = 'Draft"] click s0 "javascript:alert(1)" <script>bad</script>|`';
        $chart = (new DefinitionGraph)->mermaid(Definition::fromArray($data));
        self::assertStringNotContainsString('<script>', $chart);
        self::assertStringNotContainsString("\nclick ", $chart);
        self::assertStringNotContainsString('javascript:alert(', $chart);
        self::assertStringContainsString('#34;', $chart);
        self::assertStringContainsString('#60;', $chart);
        self::assertSame(1, substr_count($chart, 'flowchart TD'));
    }

    public function test_renders_a_dense_graph_without_losing_cycles_or_repeated_action_edges(): void
    {
        $steps = [];
        for ($index = 0; $index < 100; $index++) {
            $targets = [];
            for ($offset = 1; $offset <= 10; $offset++) {
                $targets[] = 'step'.(($index + $offset) % 100);
            }
            $actions = ['finish' => ['END']];
            for ($action = 1; $action <= 5; $action++) {
                $actions['route'.$action] = $targets;
            }
            $steps[] = ['id' => 'step'.$index, 'name' => 'Review '.$index, 'transitions' => $actions];
        }
        $definition = Definition::fromArray(['name' => 'dense-review', 'version' => 1, 'initial' => 'step0', 'steps' => $steps]);
        $chart = (new DefinitionGraph)->mermaid($definition);
        self::assertSame(5100, substr_count($chart, '-->|'));
        self::assertSame(100, substr_count($chart, '| terminal'));
        self::assertSame(1, substr_count($chart, '["Review 0"]'));
    }
}
