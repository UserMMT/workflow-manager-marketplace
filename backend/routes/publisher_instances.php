<?php

declare(strict_types=1);

use App\Controllers\PublisherInstancesController;
use App\Middleware\PublisherKeyAuthMiddleware;
use Slim\Routing\RouteCollectorProxy;

/**
 * @var \Slim\App $app
 * @var array<string, mixed> $services
 */

$publisherInstancesController = new PublisherInstancesController($services['publisherKeys']);

// Enregistrement en libre-service — public, pas de clé requise (c'est ce qui EN délivre une).
$app->post('/publisher-instances', [$publisherInstancesController, 'register']);

$app->group('/publisher-instances', function (RouteCollectorProxy $group) use ($publisherInstancesController): void {
    $group->post('/me/revoke', [$publisherInstancesController, 'revokeSelf']);
})->add(new PublisherKeyAuthMiddleware($services['publisherKeys']));
