<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Un item publié au catalogue — un WorkflowTemplate, un FormDefinition, ou un
 * "bundle" (plusieurs fichiers liés, ex. template + document de référence).
 * Le contenu réel (le JSON AWDL/formulaire) vit dans CatalogEntryVersion, pas
 * ici — même miroir versionnant que FormDefinition/FormDefinitionVersion côté
 * back_autohier/back_php : publier une nouvelle version ne modifie jamais
 * rétroactivement ce qu'une instance a déjà téléchargé.
 *
 * @property string $id
 * @property string $publisherInstanceId
 * @property string $code
 * @property string $name
 * @property string|null $description
 * @property string|null $category
 * @property string $itemType
 * @property string $status
 * @property string|null $reviewedByUserId
 * @property \Carbon\Carbon|null $reviewedAt
 * @property string|null $rejectionReason
 * @property int $downloadCount
 */
class CatalogEntry extends BaseModel
{
    protected $table = 'catalog_entry';

    const UPDATED_AT = 'updatedAt';

    protected $fillable = [
        'publisherInstanceId', 'code', 'name', 'description', 'category',
        'itemType', 'status', 'downloadCount', 'reviewedByUserId', 'reviewedAt', 'rejectionReason',
    ];

    protected $casts = [
        'downloadCount' => 'integer',
        'reviewedAt' => 'datetime',
    ];

    public function publisherInstance(): BelongsTo
    {
        return $this->belongsTo(PublisherInstance::class, 'publisherInstanceId', 'id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(CatalogEntryVersion::class, 'catalogEntryId', 'id');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewedByUserId', 'id');
    }

    /** @return array<string, mixed> */
    public function toApiArray(): array
    {
        return [
            'id' => $this->id,
            'publisherInstanceId' => $this->publisherInstanceId,
            'publisherName' => $this->relationLoaded('publisherInstance') && $this->publisherInstance !== null
                ? $this->publisherInstance->name
                : null,
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description,
            'category' => $this->category,
            'itemType' => $this->itemType,
            'status' => $this->status,
            'reviewedByUserId' => $this->reviewedByUserId,
            'reviewedByName' => $this->relationLoaded('reviewedBy') && $this->reviewedBy !== null
                ? $this->reviewedBy->name
                : null,
            'reviewedAt' => self::isoDate($this->reviewedAt),
            'rejectionReason' => $this->rejectionReason,
            'downloadCount' => $this->downloadCount,
            'createdAt' => self::isoDate($this->createdAt),
            'updatedAt' => self::isoDate($this->updatedAt),
        ];
    }
}
