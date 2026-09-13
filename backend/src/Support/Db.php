<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Database\Capsule\Manager as Capsule;

final class Db
{
    /**
     * @template T
     * @param callable(): T $callback
     * @return T
     */
    public static function transaction(callable $callback): mixed
    {
        return Capsule::connection()->transaction($callback);
    }
}
