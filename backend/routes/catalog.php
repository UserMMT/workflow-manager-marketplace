<?php

declare(strict_types=1);

use App\Controllers\CatalogController;
use App\Middleware\PublisherKeyAuthMiddleware;
use Slim\Routing\RouteCollectorProxy;

/**
 * @var \Slim\App $app
 * @var array<string, mixed> $services
 */

$catalogController = new CatalogController($services['catalog']);

// Lecture : publique, aucune clé requise.
$app->group('/catalog', function (RouteCollectorProxy $group) use ($catalogController): void {
    $group->get('', [$catalogController, 'list']);
    $group->get('/facets', [$catalogController, 'facets']);
    $group->get('/{id}', [$catalogController, 'show']);
    $group->get('/{id}/versions', [$catalogController, 'versions']);
    $group->post('/{id}/download', [$catalogController, 'downloadLatest']);
    $group->post('/{id}/versions/{versionId}/download', [$catalogController, 'downloadVersion']);
});

// Écriture : clé API d'instance éditrice requise.
$app->group('/catalog', function (RouteCollectorProxy $group) use ($catalogController): void {
    $group->post('', [$catalogController, 'submit']);
    $group->post('/{id}/versions', [$catalogController, 'addVersion']);
})->add(new PublisherKeyAuthMiddleware($services['publisherKeys']));
