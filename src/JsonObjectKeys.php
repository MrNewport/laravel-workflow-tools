<?php

declare(strict_types=1);

namespace MrNewport\LaravelWorkflowTools;

use InvalidArgumentException;

/** Checks object-key uniqueness after the native JSON decoder has validated syntax. */
final class JsonObjectKeys
{
    public static function assertUnique(string $json): void
    {
        $frames = [];
        $length = strlen($json);
        for ($index = 0; $index < $length; $index++) {
            $character = $json[$index];
            if ($character === '"') {
                $start = $index;
                while (++$index < $length) {
                    if ($json[$index] === '\\') {
                        $index++;

                        continue;
                    }
                    if ($json[$index] === '"') {
                        break;
                    }
                }
                $top = count($frames) - 1;
                if ($top >= 0 && $frames[$top]['object'] && $frames[$top]['key']) {
                    $key = json_decode(substr($json, $start, $index - $start + 1), flags: JSON_THROW_ON_ERROR);
                    if (isset($frames[$top]['seen'][':'.$key])) {
                        throw new InvalidArgumentException(Messages::get('object_keys'));
                    }
                    $frames[$top]['seen'][':'.$key] = true;
                    $frames[$top]['key'] = false;
                }
            } elseif ($character === '{' || $character === '[') {
                $frames[] = ['object' => $character === '{', 'key' => $character === '{', 'seen' => []];
            } elseif ($character === '}' || $character === ']') {
                array_pop($frames);
            } elseif ($character === ',') {
                $top = count($frames) - 1;
                if ($top >= 0 && $frames[$top]['object']) {
                    $frames[$top]['key'] = true;
                }
            }
        }
    }
}
