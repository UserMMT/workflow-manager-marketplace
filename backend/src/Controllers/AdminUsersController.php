<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\User;
use App\Services\UserAuthService;
use App\Support\JsonResponder;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/** Gestion des comptes — réservé aux admins (voir UserAuthMiddleware avec allowedRoles=['admin']). */
final class AdminUsersController
{
    use JsonResponder;

    public function __construct(private readonly UserAuthService $userAuth)
    {
    }

    public function list(Request $request, Response $response): Response
    {
        $users = $this->userAuth->listUsers();

        return $this->json($response, $users->map(fn (User $u) => $u->toApiArray())->values()->all());
    }

    public function create(Request $request, Response $response): Response
    {
        $body = $this->body($request);
        $name = $this->requireString($body, 'name');
        $email = $this->requireString($body, 'email');
        $password = $this->requireString($body, 'password', 8);
        $role = $this->requireString($body, 'role');

        $user = $this->userAuth->createUser($name, $email, $password, $role);

        return $this->json($response, $user->toApiArray(), 201);
    }
}
