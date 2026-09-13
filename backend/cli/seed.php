<?php

declare(strict_types=1);

// Peuple le catalogue à partir des fichiers catalog/templates/*.json (voir
// catalog/README.md pour le format). Idempotent : un code déjà présent dans
// le catalogue est ignoré, jamais écrasé — une version publiée reste
// immuable (voir CatalogEntryVersion), le seed ne réécrit donc rien.
//
// Les items créés ici sont directement approuvés : c'est un jeu de données
// de démo/vitrine, pas une soumission à faire revoir.
//
// Usage : php cli/seed.php

use App\Models\CatalogEntry;
use App\Models\PublisherInstance;
use App\Services\CatalogService;
use App\Services\PublisherKeyService;

$root = dirname(__DIR__);
require $root . '/vendor/autoload.php';

if (file_exists($root . '/.env')) {
    Dotenv\Dotenv::createImmutable($root)->load();
}

$settings = require $root . '/config/settings.php';
(require $root . '/bootstrap/database.php')($settings);

$catalogDir = dirname($root) . '/catalog/templates';
$files = glob($catalogDir . '/*.json') ?: [];

if ($files === []) {
    fwrite(STDOUT, "Aucun fichier de seed dans catalog/templates/.\n");
    exit(0);
}

/** @var PublisherInstance $publisher */
$publisher = PublisherInstance::where('name', 'Kibish Approbation (seed)')->first();
if ($publisher === null) {
    $publisherKeys = new PublisherKeyService($settings['publisherKeyPepper']);
    $result = $publisherKeys->register('Kibish Approbation (seed)', null);
    $publisher = PublisherInstance::find($result['id']);
    fwrite(STDOUT, "+ instance éditrice \"Kibish Approbation (seed)\" créée\n");
}

$catalog = new CatalogService();
$created = 0;
$skipped = 0;

foreach ($files as $file) {
    $data = json_decode((string) file_get_contents($file), true);
    if (!is_array($data) || !isset($data['code'], $data['name'], $data['content'])) {
        fwrite(STDERR, "! " . basename($file) . " : JSON invalide ou champs manquants, ignoré\n");
        continue;
    }

    if (CatalogEntry::where('code', $data['code'])->exists()) {
        $skipped++;
        continue;
    }

    $entry = $catalog->submit(
        $publisher,
        $data['code'],
        $data['name'],
        $data['description'] ?? null,
        $data['category'] ?? null,
        $data['itemType'] ?? 'template',
        $data['content'],
        $data['version'] ?? null,
    );

    $entry->status = 'approved';
    $entry->save();

    $created++;
}

fwrite(STDOUT, sprintf("Terminé : %d item(s) créé(s), %d déjà présent(s) (ignoré(s)).\n", $created, $skipped));
