<?php

declare(strict_types=1);

namespace MrNewport\LaravelWorkflowTools\Contracts;

use Throwable;

interface FailureReporter
{
    /** Keep exception details in the host's protected diagnostic channel. */
    public function report(Throwable $exception, string $reference): void;
}
