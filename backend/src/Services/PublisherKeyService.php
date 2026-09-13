<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\PublisherInstance;
use App\Support\ApiException;

/**
 * Enregistrement en libre-service : n'importe quelle instance Kibish
 * Approbation (back_autohier ou back_php, n'importe quelle organisation)
 * peut s'enregistrer et recevoir une clé API pour publier/télécharger.
 * Pas de validation d'identité à ce stade (v1, "init") — à durcir plus tard
 * (ex. vérification d'email) si le marketplace devient public sur Internet.
 */
final class PublisherKeyService
{
    private const KEY_PREFIX = 'wmm_';
    private const PREFIX_DISPLAY_LENGTH = 12;

    public function __construct(private readonly string $pepper)
    {
    }

    /** @return array<string, mixed> Le seul endroit où la clé brute existe encore côté serveur. */
    public function register(string $name, ?string $contactEmail): array
    {
        $rawKey = self::KEY_PREFIX . self::base64UrlEncode(random_bytes(24));
        $keyHash = hash_hmac('sha256', $rawKey, $this->pepper);

        $instance = new PublisherInstance([
            'name' => $name,
            'contactEmail' => $contactEmail,
            'apiKeyPrefix' => substr($rawKey, 0, self::PREFIX_DISPLAY_LENGTH),
            'apiKeyHash' => $keyHash,
        ]);
        $instance->save();

        $result = $instance->toApiArray();
        $result['apiKey'] = $rawKey;

        return $result;
    }

    public function verifyRawKey(string $rawKey): PublisherInstance
    {
        $keyHash = hash_hmac('sha256', $rawKey, $this->pepper);

        /** @var PublisherInstance|null $instance */
        $instance = PublisherInstance::withApiKeyHash()->where('apiKeyHash', $keyHash)->first();

        if ($instance === null) {
            throw ApiException::unauthorized('Clé API invalide.');
        }
        if ($instance->revokedAt !== null) {
            throw ApiException::unauthorized('Clé API révoquée.');
        }

        return $instance;
    }

    public function revoke(string $id): PublisherInstance
    {
        /** @var PublisherInstance|null $instance */
        $instance = PublisherInstance::find($id);
        if ($instance === null) {
            throw ApiException::notFound('Instance éditrice introuvable.');
        }

        $instance->revokedAt = new \DateTimeImmutable();
        $instance->save();

        return $instance;
    }

    private static function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
