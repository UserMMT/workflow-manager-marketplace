<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Models\UserSession;
use App\Support\ApiException;

/**
 * Comptes individuels (admin/collaborator/maintainer) et leurs sessions —
 * distinct de PublisherKeyService, qui authentifie une instance entière, pas
 * une personne. Pas d'inscription libre-service : seul un admin crée des
 * comptes (POST /api/admin/users) ; le tout premier admin est créé via
 * cli/create_user.php (chicken-and-egg sinon).
 */
final class UserAuthService
{
    private const VALID_ROLES = ['admin', 'collaborator', 'maintainer'];
    private const SESSION_TTL_DAYS = 7;
    private const MIN_PASSWORD_LENGTH = 8;

    public function __construct(private readonly string $sessionTokenPepper)
    {
    }

    public function createUser(string $name, string $email, string $password, string $role): User
    {
        if (!in_array($role, self::VALID_ROLES, true)) {
            throw ApiException::badRequest(sprintf('Rôle invalide : "%s" (attendu : %s).', $role, implode(', ', self::VALID_ROLES)));
        }
        if (strlen($password) < self::MIN_PASSWORD_LENGTH) {
            throw ApiException::badRequest(sprintf('Le mot de passe doit contenir au moins %d caractères.', self::MIN_PASSWORD_LENGTH));
        }
        if (User::where('email', $email)->exists()) {
            throw ApiException::conflict(sprintf('Un compte existe déjà avec l\'email "%s".', $email));
        }

        $user = new User([
            'name' => $name,
            'email' => $email,
            'passwordHash' => password_hash($password, PASSWORD_DEFAULT),
            'role' => $role,
        ]);
        $user->save();

        return $user;
    }

    /** @return array{token: string, user: array<string, mixed>} */
    public function login(string $email, string $password): array
    {
        /** @var User|null $user */
        $user = User::where('email', $email)->first();
        if ($user === null || !password_verify($password, $user->passwordHash)) {
            throw ApiException::unauthorized('Email ou mot de passe invalide.');
        }

        $rawToken = self::base64UrlEncode(random_bytes(32));
        $session = new UserSession([
            'userId' => $user->id,
            'tokenHash' => $this->hashToken($rawToken),
            'expiresAt' => (new \DateTimeImmutable())->modify('+' . self::SESSION_TTL_DAYS . ' days'),
        ]);
        $session->save();

        return ['token' => $rawToken, 'user' => $user->toApiArray()];
    }

    public function verifyToken(string $rawToken): User
    {
        /** @var UserSession|null $session */
        $session = UserSession::where('tokenHash', $this->hashToken($rawToken))->first();
        if ($session === null || $session->expiresAt->isPast()) {
            throw ApiException::unauthorized('Session invalide ou expirée.');
        }

        /** @var User|null $user */
        $user = User::find($session->userId);
        if ($user === null) {
            throw ApiException::unauthorized('Session invalide ou expirée.');
        }

        return $user;
    }

    public function logout(string $rawToken): void
    {
        UserSession::where('tokenHash', $this->hashToken($rawToken))->delete();
    }

    /** @return \Illuminate\Support\Collection<int, User> */
    public function listUsers(): \Illuminate\Support\Collection
    {
        return User::orderBy('createdAt', 'desc')->get();
    }

    private function hashToken(string $rawToken): string
    {
        return hash_hmac('sha256', $rawToken, $this->sessionTokenPepper);
    }

    private static function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
