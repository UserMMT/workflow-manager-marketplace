<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/** Eloquent cast for JSON columns, routed through CanonicalJson instead of Eloquent's default 'array' cast. */
final class CanonicalJsonCast implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        return $value === null ? null : CanonicalJson::decode($value);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        return $value === null ? null : CanonicalJson::encode($value);
    }
}
