<?php

declare(strict_types=1);

namespace MrNewport\LaravelWorkflowTools\Tests;

use InvalidArgumentException;
use MrNewport\LaravelWorkflowTools\DefinitionFiles;
use PHPUnit\Framework\Attributes\DataProvider;

final class DefinitionFilesTest extends TestCase
{
    public function test_round_trips_through_the_shared_definition_contract(): void
    {
        $files = new DefinitionFiles;
        $definition = $files->read($this->jsonFile($this->definitionData()));
        $restored = $files->read($this->jsonFile($files->encode($definition)));
        self::assertSame($definition->toArray(), $restored->toArray());
        self::assertSame($definition->fingerprint(), $restored->fingerprint());
    }

    #[DataProvider('invalidDocuments')]
    public function test_rejects_invalid_or_non_object_json(string $contents): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new DefinitionFiles)->read($this->jsonFile($contents));
    }

    public static function invalidDocuments(): array
    {
        return [['[]'], ['null'], ['"hello"'], ['{broken'], ["{\"name\":\"\xFF\"}"], ['<?php file_put_contents("bad", "bad");']];
    }

    public function test_rejects_stream_wrappers_without_reading_them(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new DefinitionFiles)->read('https://example.com/definition.json');
    }

    public function test_bounds_input_size(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new DefinitionFiles)->read($this->jsonFile(str_repeat(' ', DefinitionFiles::MAX_BYTES + 1)));
    }

    public function test_delegates_graph_rejection_to_the_workflow_package(): void
    {
        $invalid = $this->definitionData();
        $invalid['steps'][0]['transitions']['submit'] = ['missing'];
        $this->expectException(InvalidArgumentException::class);
        (new DefinitionFiles)->read($this->jsonFile($invalid));
    }

    #[DataProvider('duplicateKeys')]
    public function test_rejects_duplicate_object_keys_including_escaped_equivalents(string $json): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('keys must be unique');
        (new DefinitionFiles)->readArray($this->jsonFile($json));
    }

    public static function duplicateKeys(): array
    {
        return [
            ['{"name":"one","name":"two"}'],
            ['{"name":"one","na\\u006de":"two"}'],
            ['{"steps":[{"id":"one","id":"two"}]}'],
            ['{"x":{"\\u0061":1,"a":2}}'],
        ];
    }

    public function test_key_uniqueness_is_scoped_to_each_object_and_ignores_value_text(): void
    {
        $json = json_encode(['left' => ['id' => 1], 'right' => ['id' => 2], 'text' => '{"id":1,"id":2}'], JSON_THROW_ON_ERROR);
        $data = (new DefinitionFiles)->readArray($this->jsonFile($json));
        self::assertSame(['id' => 1], $data['left']);
        self::assertSame(['id' => 2], $data['right']);
    }

    public function test_preserves_large_integer_identity_values_exactly(): void
    {
        $json = '{"name":"review","version":1,"initial":"review","steps":[{"id":"review","assignment_strategy":"single_user","assignment_params":{"user_id":18446744073709551615},"transitions":{"approve":["END"]}}]}';
        $definition = (new DefinitionFiles)->read($this->jsonFile($json));
        self::assertSame('18446744073709551615', $definition->toArray()['steps'][0]['assignment_params']['user_id']);
    }

    public function test_accepts_empty_objects_and_the_exact_file_size_boundary(): void
    {
        $files = new DefinitionFiles;
        self::assertSame([], $files->readArray($this->jsonFile('{}')));
        $json = '{"x":1}';
        self::assertSame(['x' => 1], $files->readArray($this->jsonFile($json.str_repeat(' ', DefinitionFiles::MAX_BYTES - strlen($json)))));
    }
}
