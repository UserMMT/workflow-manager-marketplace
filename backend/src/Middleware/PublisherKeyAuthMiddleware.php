<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Services\PublisherKeyService;
use App\Support\ApiException;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as Handler;

/** Authentifie une instance éditrice par clé API : `Authorization: Bearer <clé>`. Pose l'attribut "publisherInstance". */
final class PublisherKeyAuthMiddleware implements MiddlewareInterface
{
    public function __construct(private readonly PublisherKeyService $publisherKeys)
    {
    }

    public function process(Request $request, Handler $handler): Response
    {
        $header = $request->getHeaderLine('Authorization');

        if (!str_starts_with($header, 'Bearer ')) {
            throw ApiException::unauthorized('Clé API manquante.');
        }

        $rawKey = substr($header, 7);
        $instance = $this->publisherKeys->verifyRawKey($rawKey);

        return $handler->handle($request->withAttribute('publisherInstance', $instance));
    }
}
