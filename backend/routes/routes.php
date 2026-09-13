<?php

declare(strict_types=1);

use Illuminate\Database\Capsule\Manager as DB;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * @var \Slim\App $app
 * @var array<string, mixed> $services
 */

require __DIR__ . '/publisher_instances.php';
require __DIR__ . '/catalog.php';
require __DIR__ . '/auth.php';
require __DIR__ . '/admin.php';

$app->get('/health', function (Request $request, Response $response) {
    $entryCount = DB::table('catalog_entry')->count();

    $response->getBody()->write((string) json_encode([
        'status' => 'ok',
        'entryCount' => $entryCount,
    ]));

    return $response->withHeader('Content-Type', 'application/json');
});
