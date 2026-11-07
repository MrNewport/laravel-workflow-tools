<?php

declare(strict_types=1);

namespace MrNewport\LaravelWorkflowTools;

use Illuminate\Support\Facades\Lang;

final class Messages
{
    /** @param array<string, int|string> $replace */
    public static function get(string $key, array $replace = []): string
    {
        return (string) Lang::get('laravel-workflow-tools::messages.'.$key, $replace);
    }
}
