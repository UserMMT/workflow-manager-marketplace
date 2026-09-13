<?php

declare(strict_types=1);

// Serveur de dev PHP intégré (`php -S ... public/index.php`) : à la
// différence d'Apache/Nginx en prod, il route TOUT à travers ce script, y
// compris les fichiers statiques existants (ex. public/admin/*) — sans ce
// garde-fou standard (skeleton Slim officiel), l'UI d'admin statique ne
// serait jamais servie, uniquement l'API.
if (PHP_SAPI === 'cli-server') {
    $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $file = __DIR__ . $path;

    // Sans le / final, les chemins relatifs (style.css, app.js) de l'index
    // servi se résoudraient contre le mauvais dossier — comme le ferait
    // Apache/Nginx, on redirige d'abord vers l'URL avec /.
    if (is_dir($file) && !str_ends_with($path, '/')) {
        header('Location: ' . $path . '/', true, 302);
        exit;
    }

    if (is_dir($file)) {
        $file = rtrim($file, '/') . '/index.html';
    }

    if (is_file($file)) {
        return false;
    }
}

$bootstrapped = require dirname(__DIR__) . '/bootstrap/app.php';

/** @var \Slim\App $app */
$app = $bootstrapped['app'];

$app->run();
