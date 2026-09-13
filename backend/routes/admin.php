<?php

declare(strict_types=1);

use App\Controllers\AdminCatalogController;
use App\Controllers\AdminUsersController;
use App\Middleware\UserAuthMiddleware;
use Slim\Routing\RouteCollectorProxy;

/**
 * @var \Slim\App $app
 * @var array<string, mixed> $services
 */

$adminCatalogController = new AdminCatalogController($services['catalog']);
$adminUsersController = new AdminUsersController($services['userAuth']);

// Modération du catalogue — n'importe quel compte authentifié (admin, collaborator, maintainer) peut approuver/rejeter.
$app->group('/admin/catalog', function (RouteCollectorProxy $group) use ($adminCatalogController): void {
    $group->get('', [$adminCatalogController, 'list']);
    $group->get('/{id}', [$adminCatalogController, 'show']);
    $group->post('/{id}/approve', [$adminCatalogController, 'approve']);
    $group->post('/{id}/reject', [$adminCatalogController, 'reject']);
})->add(new UserAuthMiddleware($services['userAuth']));

// Gestion des comptes — réservée aux admins.
$app->group('/admin/users', function (RouteCollectorProxy $group) use ($adminUsersController): void {
    $group->get('', [$adminUsersController, 'list']);
    $group->post('', [$adminUsersController, 'create']);
})->add(new UserAuthMiddleware($services['userAuth'], ['admin']));
