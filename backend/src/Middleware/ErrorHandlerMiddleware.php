<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Support\ApiException;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as Handler;
use Slim\Exception\HttpException;

final class ErrorHandlerMiddleware implements MiddlewareInterface
{
    public function __construct(private readonly ResponseFactoryInterface $responseFactory)
    {
    }

    public function process(Request $request, Handler $handler): Response
    {
        try {
            return $handler->handle($request);
        } catch (ApiException $e) {
            return $this->json($e->getStatus(), ['message' => $e->getMessage()]);
        } catch (HttpException $e) {
            return $this->json($e->getCode(), ['message' => $e->getMessage()]);
        } catch (\Throwable $e) {
            error_log((string) $e);

            return $this->json(500, ['message' => 'Internal server error']);
        }
    }

    /** @param array<string, mixed> $payload */
    private function json(int $status, array $payload): Response
    {
        $response = $this->responseFactory->createResponse($status);
        $response->getBody()->write((string) json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        return $response->withHeader('Content-Type', 'application/json');
    }
}
