<?php

declare(strict_types=1);

// Pas de framework de migrations (même philosophie que back_php) : ce script
// crée les tables si elles n'existent pas encore, rejouable sans risque.
//
// Usage : php cli/migrate.php

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;

$root = dirname(__DIR__);
require $root . '/vendor/autoload.php';

if (file_exists($root . '/.env')) {
    Dotenv\Dotenv::createImmutable($root)->load();
}

$settings = require $root . '/config/settings.php';
(require $root . '/bootstrap/database.php')($settings);

$schema = Capsule::schema();

if (!$schema->hasTable('publisher_instance')) {
    $schema->create('publisher_instance', function (Blueprint $table): void {
        $table->string('id')->primary();
        $table->string('name');
        $table->string('contactEmail')->nullable();
        $table->string('apiKeyPrefix');
        $table->string('apiKeyHash');
        $table->dateTime('revokedAt')->nullable();
        $table->dateTime('createdAt');
    });
    fwrite(STDOUT, "+ table publisher_instance créée\n");
} else {
    fwrite(STDOUT, "= table publisher_instance déjà présente\n");
}

if (!$schema->hasTable('catalog_entry')) {
    $schema->create('catalog_entry', function (Blueprint $table): void {
        $table->string('id')->primary();
        $table->string('publisherInstanceId');
        $table->foreign('publisherInstanceId')->references('id')->on('publisher_instance')->onDelete('cascade');
        $table->string('code')->unique();
        $table->string('name');
        $table->text('description')->nullable();
        $table->string('category')->nullable();
        $table->string('itemType'); // template | form | bundle
        $table->boolean('isPublished')->default(true);
        $table->integer('downloadCount')->default(0);
        $table->dateTime('createdAt');
        $table->dateTime('updatedAt');
    });
    fwrite(STDOUT, "+ table catalog_entry créée\n");
} else {
    fwrite(STDOUT, "= table catalog_entry déjà présente\n");
}

if (!$schema->hasTable('catalog_entry_version')) {
    $schema->create('catalog_entry_version', function (Blueprint $table): void {
        $table->string('id')->primary();
        $table->string('catalogEntryId');
        $table->foreign('catalogEntryId')->references('id')->on('catalog_entry')->onDelete('cascade');
        $table->integer('versionNumber');
        $table->string('version');
        $table->text('content');
        $table->string('notes')->nullable();
        $table->dateTime('createdAt');
    });
    fwrite(STDOUT, "+ table catalog_entry_version créée\n");
} else {
    fwrite(STDOUT, "= table catalog_entry_version déjà présente\n");
}

if (!$schema->hasTable('user')) {
    $schema->create('user', function (Blueprint $table): void {
        $table->string('id')->primary();
        $table->string('name');
        $table->string('email')->unique();
        $table->string('passwordHash');
        $table->string('role'); // admin | collaborator | maintainer
        $table->dateTime('createdAt');
    });
    fwrite(STDOUT, "+ table user créée\n");
} else {
    fwrite(STDOUT, "= table user déjà présente\n");
}

if (!$schema->hasTable('user_session')) {
    $schema->create('user_session', function (Blueprint $table): void {
        $table->string('id')->primary();
        $table->string('userId');
        $table->foreign('userId')->references('id')->on('user')->onDelete('cascade');
        $table->string('tokenHash')->unique();
        $table->dateTime('expiresAt');
        $table->dateTime('createdAt');
    });
    fwrite(STDOUT, "+ table user_session créée\n");
} else {
    fwrite(STDOUT, "= table user_session déjà présente\n");
}

// Modération : ajoutée après le scaffold initial ("État actuel" du README la
// listait comme non construite) — catalog_entry existait déjà sans ces
// colonnes, donc ajout de colonnes + backfill plutôt que recréation.
if (!$schema->hasColumn('catalog_entry', 'status')) {
    $schema->table('catalog_entry', function (Blueprint $table): void {
        $table->string('status')->default('pending'); // pending | approved | rejected
        $table->string('reviewedByUserId')->nullable();
        $table->dateTime('reviewedAt')->nullable();
        $table->text('rejectionReason')->nullable();
    });
    // Les items déjà publiés avant l'introduction de la modération restent
    // visibles publiquement — pas de régression pour les données existantes.
    Capsule::table('catalog_entry')->where('isPublished', true)->update(['status' => 'approved']);
    fwrite(STDOUT, "+ colonnes de modération ajoutées à catalog_entry (items déjà publiés migrés en 'approved')\n");
} else {
    fwrite(STDOUT, "= colonnes de modération déjà présentes sur catalog_entry\n");
}

fwrite(STDOUT, "Terminé.\n");
