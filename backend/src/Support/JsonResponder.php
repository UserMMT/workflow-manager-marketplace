<?php

declare(strict_types=1);

namespace App\Support;

use Psr\Http\Message\ResponseInterface as Response;

trait JsonResponder
{
    /** @param array<string, mixed>|array<int, mixed> $payload */
    private function json(Response $response, array $payload, int $status = 200): Response
    {
        $response->getBody()->write((string) json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
    }

    /** @return array<string, mixed> */
    private function body(\Psr\Http\Message\ServerRequestInterface $request): array
    {
        $parsed = $request->getParsedBody();

        return is_array($parsed) ? $parsed : [];
    }

    /** @param array<string, mixed> $body */
    private function requireString(array $body, string $key, ?int $minLength = null): string
    {
        $value = $body[$key] ?? null;

        if (!is_string($value) || $value === '' || ($minLength !== null && strlen($value) < $minLength)) {
            throw ApiException::badRequest("Invalid or missing field: {$key}");
        }

        return $value;
    }

    private function optionalString(array $body, string $key): ?string
    {
        $value = $body[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * @param array<string, mixed> $queryParams
     * @return array{0: int, 1: int} [page, perPage] — page >= 1, 1 <= perPage <= 100 (défaut 20).
     */
    private function paginationParams(array $queryParams): array
    {
        $page = max(1, (int) ($queryParams['page'] ?? 1));
        $perPage = (int) ($queryParams['perPage'] ?? 20);
        $perPage = $perPage > 0 ? min(100, $perPage) : 20;

        return [$page, $perPage];
    }

    /** @param array{items: \Illuminate\Support\Collection<int, mixed>, total: int} $result */
    private function paginatedJson(Response $response, array $result, int $page, int $perPage, \Closure $toApiArray): Response
    {
        return $this->json($response, [
            'items' => $result['items']->map($toApiArray)->values()->all(),
            'page' => $page,
            'perPage' => $perPage,
            'total' => $result['total'],
            'totalPages' => (int) max(1, ceil($result['total'] / $perPage)),
        ]);
    }
}
