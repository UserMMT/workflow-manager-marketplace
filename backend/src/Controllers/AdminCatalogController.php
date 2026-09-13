<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\CatalogEntry;
use App\Models\CatalogEntryVersion;
use App\Services\CatalogService;
use App\Support\JsonResponder;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/** Modération du catalogue — réservé aux comptes authentifiés (admin/collaborator/maintainer, voir UserAuthMiddleware). */
final class AdminCatalogController
{
    use JsonResponder;

    public function __construct(private readonly CatalogService $catalog)
    {
    }

    /** ?status=pending|approved|rejected — omis, renvoie tous les statuts. */
    public function list(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();
        [$page, $perPage] = $this->paginationParams($params);
        $result = $this->catalog->findAllForReview($params['status'] ?? null, $page, $perPage);

        return $this->paginatedJson($response, $result, $page, $perPage, fn (CatalogEntry $e) => $e->toApiArray());
    }

    /** Détail + historique des versions, pour lire le contenu réel avant d'approuver/rejeter. */
    public function show(Request $request, Response $response, array $args): Response
    {
        $entry = $this->catalog->findOneForReview($args['id']);

        $data = $entry->toApiArray();
        $data['versions'] = $entry->versions
            ->sortByDesc('versionNumber')
            ->map(fn (CatalogEntryVersion $v) => $v->toApiArray())
            ->values()
            ->all();

        return $this->json($response, $data);
    }

    public function approve(Request $request, Response $response, array $args): Response
    {
        $reviewer = $request->getAttribute('currentUser');
        $entry = $this->catalog->approve($args['id'], $reviewer);

        return $this->json($response, $entry->toApiArray());
    }

    public function reject(Request $request, Response $response, array $args): Response
    {
        $body = $this->body($request);
        $reason = $this->optionalString($body, 'reason');

        $reviewer = $request->getAttribute('currentUser');
        $entry = $this->catalog->reject($args['id'], $reviewer, $reason);

        return $this->json($response, $entry->toApiArray());
    }
}
