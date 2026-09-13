<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\CanonicalJsonCast;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Snapshot du contenu réel d'un CatalogEntry à une version donnée — jamais
 * réécrit, jamais supprimé. `content` porte la charge utile telle quelle
 * (config AWDL complète pour itemType=template, tableau de champs pour
 * itemType=form, manifeste { files: [...] } pour itemType=bundle) : ce
 * marketplace ne valide pas la forme du contenu, une instance qui télécharge
 * revalide toujours via son propre AwdlValidatorService avant import.
 *
 * @property string $id
 * @property string $catalogEntryId
 * @property int $versionNumber
 * @property string $version
 * @property array<string, mixed> $content
 * @property string|null $notes
 */
class CatalogEntryVersion extends BaseModel
{
    protected $table = 'catalog_entry_version';

    protected $fillable = ['catalogEntryId', 'versionNumber', 'version', 'content', 'notes'];

    protected $casts = [
        'content' => CanonicalJsonCast::class,
    ];

    public function catalogEntry(): BelongsTo
    {
        return $this->belongsTo(CatalogEntry::class, 'catalogEntryId', 'id');
    }

    /** @return array<string, mixed> */
    public function toApiArray(): array
    {
        return [
            'id' => $this->id,
            'catalogEntryId' => $this->catalogEntryId,
            'versionNumber' => $this->versionNumber,
            'version' => $this->version,
            'content' => $this->content,
            'notes' => $this->notes,
            'createdAt' => self::isoDate($this->createdAt),
        ];
    }
}
