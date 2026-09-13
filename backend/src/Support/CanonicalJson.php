<?php

declare(strict_types=1);

namespace App\Support;

/** JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE — the house convention for every JSON column/response in this codebase family. */
final class CanonicalJson
{
    public static function encode(mixed $value): string
    {
        return (string) json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    public static function decode(string $json): mixed
    {
        return json_decode($json, true);
    }
}
