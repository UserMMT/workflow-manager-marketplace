<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\CatalogEntry;
use App\Models\CatalogEntryVersion;
use App\Services\CatalogService;
use App\Support\ApiException;
use App\Support\JsonResponder;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class CatalogController
{
    use JsonResponder;

    public function __construct(private readonly CatalogService $catalog)
    {
    }

    /** Public — aucune clé requise, n'importe quelle instance/site peut parcourir le catalogue. */
    public function list(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();
        [$page, $perPage] = $this->paginationParams($params);
        $result = $this->catalog->findAll($params['category'] ?? null, $params['itemType'] ?? null, $params['search'] ?? null, $page, $perPage);

        return $this->paginatedJson($response, $result, $page, $perPage, fn (CatalogEntry $e) => $e->toApiArray());
    }

    /** Catégories et types présents dans le catalogue — alimente les filtres du front public. */
    public function facets(Request $request, Response $response): Response
    {
        return $this->json($response, $this->catalog->facets());
    }

    public function show(Request $request, Response $response, array $args): Response
    {
        return $this->json($response, $this->catalog->findOne($args['id'])->toApiArray());
    }

    public function versions(Request $request, Response $response, array $args): Response
    {
        $versions = $this->catalog->findVersions($args['id']);

        return $this->json($response, $versions->map(fn (CatalogEntryVersion $v) => $v->toApiArray())->values()->all());
    }

    /** Incrémente le compteur de téléchargements — reste public (mêmes règles de visibilité que list/show). */
    public function downloadLatest(Request $request, Response $response, array $args): Response
    {
        $latest = $this->catalog->findLatestVersion($args['id']);
        $version = $this->catalog->download($args['id'], $latest->id);

        return $this->json($response, $version->toApiArray());
    }

    public function downloadVersion(Request $request, Response $response, array $args): Response
    {
        $version = $this->catalog->download($args['id'], $args['versionId']);

        return $this->json($response, $version->toApiArray());
    }

    /** Requiert une clé API d'instance éditrice (PublisherKeyAuthMiddleware). */
    public function submit(Request $request, Response $response): Response
    {
        $body = $this->body($request);
        $code = $this->requireString($body, 'code');
        $name = $this->requireString($body, 'name');
        $itemType = $this->requireString($body, 'itemType');
        $content = $body['content'] ?? null;
        if (!is_array($content)) {
            throw ApiException::badRequest('Invalid or missing field: content');
        }

        $publisher = $request->getAttribute('publisherInstance');
        $entry = $this->catalog->submit(
            $publisher,
            $code,
            $name,
            $this->optionalString($body, 'description'),
            $this->optionalString($body, 'category'),
            $itemType,
            $content,
            $this->optionalString($body, 'version'),
        );

        return $this->json($response, $entry->toApiArray(), 201);
    }

    public function addVersion(Request $request, Response $response, array $args): Response
    {
        $body = $this->body($request);
        $content = $body['content'] ?? null;
        if (!is_array($content)) {
            throw ApiException::badRequest('Invalid or missing field: content');
        }

        $publisher = $request->getAttribute('publisherInstance');
        $version = $this->catalog->addVersion(
            $publisher,
            $args['id'],
            $content,
            $this->optionalString($body, 'version'),
            $this->optionalString($body, 'notes'),
        );

        return $this->json($response, $version->toApiArray(), 201);
    }
}
