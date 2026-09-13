<?php

declare(strict_types=1);

use App\Controllers\AuthController;
use App\Middleware\UserAuthMiddleware;
use Slim\Routing\RouteCollectorProxy;

/**
 * @var \Slim\App $app
 * @var array<string, mixed> $services
 */

$authController = new AuthController($services['userAuth']);

// Connexion — publique, c'est ce qui délivre le jeton de session.
$app->post('/auth/login', [$authController, 'login']);

$app->group('/auth', function (RouteCollectorProxy $group) use ($authController): void {
    $group->post('/logout', [$authController, 'logout']);
    $group->get('/me', [$authController, 'me']);
})->add(new UserAuthMiddleware($services['userAuth']));
