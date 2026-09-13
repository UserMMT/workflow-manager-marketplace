<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\PublisherKeyService;
use App\Support\JsonResponder;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class PublisherInstancesController
{
    use JsonResponder;

    public function __construct(private readonly PublisherKeyService $publisherKeys)
    {
    }

    /** Enregistrement en libre-service — voir PublisherKeyService pour les limites actuelles. */
    public function register(Request $request, Response $response): Response
    {
        $body = $this->body($request);
        $name = $this->requireString($body, 'name');
        $contactEmail = $this->optionalString($body, 'contactEmail');

        $result = $this->publisherKeys->register($name, $contactEmail);

        return $this->json($response, $result, 201);
    }

    /**
     * Révoque SA PROPRE clé — pas de paramètre d'id : uniquement soi-même,
     * jamais une autre instance (pas de rôle "admin du marketplace" en v1).
     */
    public function revokeSelf(Request $request, Response $response): Response
    {
        $publisher = $request->getAttribute('publisherInstance');
        $instance = $this->publisherKeys->revoke($publisher->id);

        return $this->json($response, $instance->toApiArray());
    }
}
