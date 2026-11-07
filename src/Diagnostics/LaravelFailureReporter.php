<?php

declare(strict_types=1);

namespace MrNewport\LaravelWorkflowTools\Diagnostics;

use Illuminate\Contracts\Debug\ExceptionHandler;
use MrNewport\LaravelWorkflowTools\Contracts\FailureReporter;
use RuntimeException;
use Throwable;

final class LaravelFailureReporter implements FailureReporter
{
    public function __construct(private ExceptionHandler $handler) {}

    public function report(Throwable $exception, string $reference): void
    {
        $this->handler->report(new RuntimeException('Workflow command failure. Reference: '.$reference, previous: $exception));
    }
}
