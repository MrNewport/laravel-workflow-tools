<?php

declare(strict_types=1);

namespace MrNewport\LaravelWorkflowTools;

use InvalidArgumentException;
use JsonException;
use MrNewport\LaravelWorkflow\Definition;
use RuntimeException;

/** JSON data only: never load definition files as PHP or through URL wrappers. */
final class DefinitionFiles
{
    public const MAX_BYTES = 1_048_576;

    public function read(string $path): Definition
    {
        return Definition::fromArray($this->readArray($path));
    }

    /** @return array<string, mixed> */
    public function readArray(string $path): array
    {
        if ($path === '' || str_contains($path, "\0") || str_contains($path, '://')) {
            throw new InvalidArgumentException(Messages::get('file_path'));
        }

        $resolved = realpath($path);
        if ($resolved === false || ! is_file($resolved) || ! is_readable($resolved)) {
            throw new InvalidArgumentException(Messages::get('file_unreadable'));
        }

        $stream = @fopen($resolved, 'rb');
        if ($stream === false) {
            throw new RuntimeException(Messages::get('file_open'));
        }

        try {
            $contents = stream_get_contents($stream, self::MAX_BYTES + 1);
        } finally {
            fclose($stream);
        }

        if ($contents === false || strlen($contents) > self::MAX_BYTES) {
            throw new InvalidArgumentException(Messages::get('file_size'));
        }

        try {
            $data = json_decode($contents, true, 128, JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING);
        } catch (JsonException $exception) {
            throw new InvalidArgumentException(Messages::get('file_json'), previous: $exception);
        }

        if (! is_array($data) || ! str_starts_with(ltrim($contents), '{')) {
            throw new InvalidArgumentException(Messages::get('file_object'));
        }
        JsonObjectKeys::assertUnique($contents);

        return $data;
    }

    public function encode(Definition $definition): string
    {
        $flags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR;
        $pretty = json_encode($definition->toArray(), $flags | JSON_PRETTY_PRINT)."\n";
        if (strlen($pretty) <= self::MAX_BYTES) {
            return $pretty;
        }

        $compact = json_encode($definition->toArray(), $flags)."\n";
        if (strlen($compact) > self::MAX_BYTES) {
            throw new InvalidArgumentException(Messages::get('export_size'));
        }

        return $compact;
    }
}
