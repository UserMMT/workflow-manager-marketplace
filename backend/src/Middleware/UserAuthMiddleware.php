<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Services\UserAuthService;
use App\Support\ApiException;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as Handler;

/**
 * Authentifie un compte individuel par jeton de session : `Authorization: Bearer <jeton>`.
 * Pose l'attribut "currentUser". Avec $allowedRoles non vide, exige en plus
 * que le rôle du compte y figure (ex. ['admin'] pour la gestion des comptes) —
 * un seul paramètre plutôt qu'un middleware de rôle séparé, pour éviter tout
 * souci d'ordre d'exécution entre deux middlewares empilés.
 */
final class UserAuthMiddleware implements MiddlewareInterface
{
    /** @param list<string> $allowedRoles */
    public function __construct(
        private readonly UserAuthService $userAuth,
        private readonly array $allowedRoles = [],
    ) {
    }

    public function process(Request $request, Handler $handler): Response
    {
        $header = $request->getHeaderLine('Authorization');

        if (!str_starts_with($header, 'Bearer ')) {
            throw ApiException::unauthorized('Authentification requise.');
        }

        $user = $this->userAuth->verifyToken(substr($header, 7));

        if ($this->allowedRoles !== [] && !in_array($user->role, $this->allowedRoles, true)) {
            throw ApiException::forbidden('Rôle insuffisant pour cette action.');
        }

        return $handler->handle($request->withAttribute('currentUser', $user));
    }
}
