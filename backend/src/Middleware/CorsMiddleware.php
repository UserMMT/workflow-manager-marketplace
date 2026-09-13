<?php

declare(strict_types=1);

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as Handler;

/** Le catalogue est public : CORS grand ouvert, n'importe quelle instance/site doit pouvoir l'interroger. */
final class CorsMiddleware implements MiddlewareInterface
{
    public function process(Request $request, Handler $handler): Response
    {
        if (strtoupper($request->getMethod()) === 'OPTIONS') {
            $response = new \Slim\Psr7\Response();

            return $this->withCorsHeaders($response, $request);
        }

        $response = $handler->handle($request);

        return $this->withCorsHeaders($response, $request);
    }

    private function withCorsHeaders(Response $response, Request $request): Response
    {
        $origin = $request->getHeaderLine('Origin') ?: '*';

        return $response
            ->withHeader('Access-Control-Allow-Origin', $origin)
            ->withHeader('Access-Control-Allow-Credentials', 'true')
            ->withHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization')
            ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS');
    }
}
