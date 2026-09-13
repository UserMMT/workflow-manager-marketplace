<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\UserAuthService;
use App\Support\JsonResponder;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class AuthController
{
    use JsonResponder;

    public function __construct(private readonly UserAuthService $userAuth)
    {
    }

    /** Public — email + mot de passe, retourne un jeton de session. */
    public function login(Request $request, Response $response): Response
    {
        $body = $this->body($request);
        $email = $this->requireString($body, 'email');
        $password = $this->requireString($body, 'password');

        $result = $this->userAuth->login($email, $password);

        return $this->json($response, $result);
    }

    public function logout(Request $request, Response $response): Response
    {
        $header = $request->getHeaderLine('Authorization');
        $this->userAuth->logout(substr($header, 7));

        return $this->json($response, ['message' => 'Déconnecté.']);
    }

    public function me(Request $request, Response $response): Response
    {
        $user = $request->getAttribute('currentUser');

        return $this->json($response, $user->toApiArray());
    }
}
