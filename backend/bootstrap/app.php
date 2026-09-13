<?php

declare(strict_types=1);

use App\Middleware\CorsMiddleware;
use App\Middleware\ErrorHandlerMiddleware;
use App\Services\CatalogService;
use App\Services\PublisherKeyService;
use App\Services\UserAuthService;
use Dotenv\Dotenv;
use Slim\Factory\AppFactory;

require __DIR__ . '/../vendor/autoload.php';

$root = dirname(__DIR__);

if (file_exists($root . '/.env')) {
    Dotenv::createImmutable($root)->load();
}

$settings = require $root . '/config/settings.php';

(require __DIR__ . '/database.php')($settings);

// Locator de services minimal — même convention que back_php/bootstrap/app.php.
$services = [
    'publisherKeys' => new PublisherKeyService($settings['publisherKeyPepper']),
    'catalog' => new CatalogService(),
    'userAuth' => new UserAuthService($settings['sessionTokenPepper']),
];

$app = AppFactory::create();

$app->addBodyParsingMiddleware();
$app->addRoutingMiddleware();
$app->add(new CorsMiddleware());
$app->add(new ErrorHandlerMiddleware($app->getResponseFactory()));

$app->setBasePath('/' . trim((string) $settings['apiPrefix'], '/'));

require $root . '/routes/routes.php';

return ['app' => $app, 'settings' => $settings, 'services' => $services];
