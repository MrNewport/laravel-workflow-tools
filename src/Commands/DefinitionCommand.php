<?php

declare(strict_types=1);

namespace MrNewport\LaravelWorkflowTools\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\QueryException;
use InvalidArgumentException;
use LogicException;
use MrNewport\LaravelWorkflowTools\Contracts\FailureReporter;
use MrNewport\LaravelWorkflowTools\Messages;
use RuntimeException;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

abstract class DefinitionCommand extends Command
{
    public function getDescription(): string
    {
        $key = 'commands.'.$this->getName();
        $translated = Messages::get($key);

        return $translated === 'laravel-workflow-tools::messages.'.$key ? parent::getDescription() : $translated;
    }

    protected function executeSafely(callable $operation): int
    {
        try {
            return $operation();
        } catch (Throwable $exception) {
            $message = $exception instanceof QueryException
                ? Messages::get('database_failure')
                : (($exception instanceof InvalidArgumentException || $exception instanceof LogicException || $exception instanceof RuntimeException)
                    ? $exception->getMessage()
                    : Messages::get('operation_failure'));
            if (! $exception instanceof InvalidArgumentException && ! $exception instanceof LogicException) {
                $reference = bin2hex(random_bytes(8));
                try {
                    app(FailureReporter::class)->report($exception, $reference);
                } catch (Throwable) {
                    // A failed reporter must not replace the original command failure.
                }
                $message .= ' '.Messages::get('failure_reference', ['reference' => $reference]);
            }
            $this->error(OutputFormatter::escape($message));

            return self::FAILURE;
        }
    }

    /** @param array<array-key, mixed> $data */
    protected function writeJson(array $data): void
    {
        // Raw writes preserve valid JSON; Symfony formatting must not rewrite values.
        $this->writeRaw(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)."\n");
    }

    protected function writeRaw(string $output): void
    {
        $this->output->getOutput()->write($output, false, OutputInterface::OUTPUT_RAW);
    }
}
