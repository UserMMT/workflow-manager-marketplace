<?php

declare(strict_types=1);

use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Events\Dispatcher;

/**
 * @param array<string, mixed> $settings
 */
return function (array $settings): Capsule {
    // Illuminate\Database\Connectors\SQLiteConnector::parseDatabasePath() does
    // `realpath($path) ?: realpath(base_path($path))` — on a file that doesn't
    // exist YET (first boot, before any table has ever been created),
    // realpath() returns false and it falls through to base_path(), a
    // full-Laravel helper absent from this standalone Capsule setup. Creating
    // the (empty) file first makes realpath() succeed on the first branch.
    if ($settings['dbDatabase'] !== ':memory:' && !file_exists($settings['dbDatabase'])) {
        touch($settings['dbDatabase']);
    }

    $capsule = new Capsule();

    $capsule->addConnection([
        'driver' => 'sqlite',
        'database' => $settings['dbDatabase'],
        'prefix' => '',
        'foreign_key_constraints' => true,
    ]);

    // Standalone Capsule has no event dispatcher by default, which silently
    // no-ops model events (creating/saving/...) — required for our UUID PK
    // generation hook (BaseModel::boot()) to fire at all.
    $capsule->setEventDispatcher(new Dispatcher(new Container()));

    $capsule->setAsGlobal();
    $capsule->bootEloquent();

    // PDO's sqlite driver does NOT enable FK enforcement by default. Must be
    // set explicitly per connection or cascading deletes / FK-violation 409s
    // silently misbehave.
    $capsule->getConnection()->getPdo()->exec('PRAGMA foreign_keys = ON;');

    return $capsule;
};
