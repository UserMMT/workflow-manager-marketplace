<?php

declare(strict_types=1);

// Résolution du chemin DB relative contre la racine du projet, pas le cwd du
// process — même piège/fix que back_php/config/settings.php (voir ce fichier
// pour l'explication complète : Illuminate\Database\Connectors\SQLiteConnector
// dépend de `base_path()`, un helper Laravel absent de ce setup Capsule autonome).
$dbDatabase = $_ENV['DB_DATABASE'] ?? './marketplace.sqlite';
$dbDatabaseIsAbsolute = $dbDatabase === ':memory:'
    || str_starts_with($dbDatabase, '/')
    || preg_match('#^[A-Za-z]:[\\\\/]#', $dbDatabase) === 1;
if (!$dbDatabaseIsAbsolute) {
    $dbDatabase = dirname(__DIR__) . '/' . preg_replace('#^\./#', '', $dbDatabase);
}

return [
    'port' => (int) ($_ENV['PORT'] ?? 3030),
    'apiPrefix' => $_ENV['API_PREFIX'] ?? 'api',
    'dbDatabase' => $dbDatabase,
    'publisherKeyPepper' => $_ENV['PUBLISHER_KEY_PEPPER'] ?? 'change-this-in-production',
    'sessionTokenPepper' => $_ENV['SESSION_TOKEN_PEPPER'] ?? 'change-this-in-production',
    'frontendUrl' => $_ENV['FRONTEND_URL'] ?? 'http://localhost:3031',
];
