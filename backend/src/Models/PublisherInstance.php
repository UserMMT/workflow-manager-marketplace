<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Une instance Kibish Approbation (une organisation qui exécute back_autohier
 * ou back_php) enregistrée pour publier/télécharger sur le marketplace.
 * Clé API propre à ce service — sans rapport avec ApiToken côté back_autohier/
 * back_php (ceux-là authentifient un appelant AU SEIN d'une instance ; celle-ci
 * authentifie une instance entière AUPRÈS du marketplace).
 *
 * @property string $id
 * @property string $name
 * @property string|null $contactEmail
 * @property string $apiKeyPrefix
 * @property string|null $apiKeyHash
 * @property \Carbon\Carbon|null $revokedAt
 */
class PublisherInstance extends BaseModel
{
    protected $table = 'publisher_instance';

    protected $fillable = ['name', 'contactEmail', 'apiKeyPrefix', 'apiKeyHash', 'revokedAt'];

    protected $casts = [
        'revokedAt' => 'datetime',
    ];

    /** Colonnes visibles par défaut — apiKeyHash ne doit jamais fuiter via une réponse normale. */
    private const VISIBLE_COLUMNS = ['id', 'name', 'contactEmail', 'apiKeyPrefix', 'revokedAt', 'createdAt'];

    protected static function booted(): void
    {
        static::addGlobalScope('hideApiKeyHash', function (Builder $builder): void {
            if ($builder->getQuery()->columns === null) {
                $builder->select(array_map(fn (string $c) => 'publisher_instance.' . $c, self::VISIBLE_COLUMNS));
            }
        });
    }

    /** Escape hatch — seul PublisherKeyService::verifyRawKey() en a légitimement besoin. */
    public static function withApiKeyHash(): Builder
    {
        return static::withoutGlobalScope('hideApiKeyHash');
    }

    public function catalogEntries(): HasMany
    {
        return $this->hasMany(CatalogEntry::class, 'publisherInstanceId', 'id');
    }

    /** @return array<string, mixed> */
    public function toApiArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'contactEmail' => $this->contactEmail,
            'apiKeyPrefix' => $this->apiKeyPrefix,
            'revokedAt' => self::isoDate($this->revokedAt),
            'createdAt' => self::isoDate($this->createdAt),
        ];
    }
}
