<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\CatalogEntry;
use App\Models\CatalogEntryVersion;
use App\Models\PublisherInstance;
use App\Models\User;
use App\Support\ApiException;
use App\Support\Db;

/** Item = template/form/bundle publié ; version = snapshot immuable de son contenu (voir CatalogEntryVersion). */
final class CatalogService
{
    private const VALID_ITEM_TYPES = ['template', 'form', 'bundle'];
    private const VALID_STATUSES = ['pending', 'approved', 'rejected'];

    /** @param array<string, mixed> $content */
    public function submit(
        PublisherInstance $publisher,
        string $code,
        string $name,
        ?string $description,
        ?string $category,
        string $itemType,
        array $content,
        ?string $version,
    ): CatalogEntry {
        if (!in_array($itemType, self::VALID_ITEM_TYPES, true)) {
            throw ApiException::badRequest(sprintf('itemType invalide : "%s" (attendu : %s).', $itemType, implode(', ', self::VALID_ITEM_TYPES)));
        }
        if (CatalogEntry::where('code', $code)->exists()) {
            throw ApiException::conflict(sprintf('Le code "%s" est déjà utilisé dans le catalogue.', $code));
        }

        return Db::transaction(function () use ($publisher, $code, $name, $description, $category, $itemType, $content, $version) {
            $entry = new CatalogEntry([
                'publisherInstanceId' => $publisher->id,
                'code' => $code,
                'name' => $name,
                'description' => $description,
                'category' => $category,
                'itemType' => $itemType,
                'status' => 'pending',
                'downloadCount' => 0,
            ]);
            $entry->save();

            $this->snapshotVersion($entry, $content, $version ?? '1.0.0', null);

            return $entry;
        });
    }

    /**
     * Publier une nouvelle version repasse l'item en attente de modération —
     * même s'il était déjà "approved" : le contenu a changé, un reviewer doit
     * le revoir avant qu'il ne redevienne visible publiquement.
     *
     * @param array<string, mixed> $content
     */
    public function addVersion(PublisherInstance $publisher, string $entryId, array $content, ?string $version, ?string $notes): CatalogEntryVersion
    {
        $entry = $this->findOwnedEntry($publisher, $entryId);
        $last = CatalogEntryVersion::where('catalogEntryId', $entry->id)->orderBy('versionNumber', 'desc')->first();

        $snapshot = $this->snapshotVersion($entry, $content, $version ?? ($last->version ?? '1.0.0'), $notes);

        $entry->status = 'pending';
        $entry->reviewedByUserId = null;
        $entry->reviewedAt = null;
        $entry->rejectionReason = null;
        $entry->save();

        return $snapshot;
    }

    /** @return array{items: \Illuminate\Support\Collection<int, CatalogEntry>, total: int} */
    public function findAll(?string $category, ?string $itemType, ?string $search, int $page, int $perPage): array
    {
        $query = CatalogEntry::where('status', 'approved')->with('publisherInstance');

        if ($category !== null) {
            $query->where('category', $category);
        }
        if ($itemType !== null) {
            $query->where('itemType', $itemType);
        }
        if ($search !== null) {
            $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $search) . '%';
            $query->where(function ($q) use ($like) {
                $q->where('name', 'like', $like)->orWhere('description', 'like', $like);
            });
        }

        $total = (clone $query)->count();
        $items = $query->orderBy('createdAt', 'desc')->forPage($page, $perPage)->get();

        return ['items' => $items, 'total' => $total];
    }

    /** Catégories et types présents dans le catalogue public — alimente les filtres du front. */
    public function facets(): array
    {
        $categories = CatalogEntry::where('status', 'approved')
            ->whereNotNull('category')
            ->distinct()
            ->orderBy('category')
            ->pluck('category')
            ->values()
            ->all();

        $itemTypes = CatalogEntry::where('status', 'approved')
            ->distinct()
            ->orderBy('itemType')
            ->pluck('itemType')
            ->values()
            ->all();

        return ['categories' => $categories, 'itemTypes' => $itemTypes];
    }

    public function findOne(string $id): CatalogEntry
    {
        /** @var CatalogEntry|null $entry */
        $entry = CatalogEntry::with('publisherInstance')->where('id', $id)->where('status', 'approved')->first();
        if ($entry === null) {
            throw ApiException::notFound(sprintf('Item de catalogue "%s" introuvable.', $id));
        }

        return $entry;
    }

    /** @return \Illuminate\Support\Collection<int, CatalogEntryVersion> */
    public function findVersions(string $entryId): \Illuminate\Support\Collection
    {
        $this->findOne($entryId);

        return CatalogEntryVersion::where('catalogEntryId', $entryId)->orderBy('versionNumber', 'desc')->get();
    }

    /** Incrémente le compteur de téléchargements et renvoie le contenu — appelé à chaque téléchargement effectif. */
    public function download(string $entryId, string $versionId): CatalogEntryVersion
    {
        $this->findOne($entryId);

        /** @var CatalogEntryVersion|null $version */
        $version = CatalogEntryVersion::where('id', $versionId)->where('catalogEntryId', $entryId)->first();
        if ($version === null) {
            throw ApiException::notFound('Version introuvable.');
        }

        CatalogEntry::where('id', $entryId)->increment('downloadCount');

        return $version;
    }

    /** Dernière version (la plus récente) d'un item — pratique pour un téléchargement "toujours à jour". */
    public function findLatestVersion(string $entryId): CatalogEntryVersion
    {
        $this->findOne($entryId);

        /** @var CatalogEntryVersion|null $version */
        $version = CatalogEntryVersion::where('catalogEntryId', $entryId)->orderBy('versionNumber', 'desc')->first();
        if ($version === null) {
            throw ApiException::notFound('Aucune version disponible pour cet item.');
        }

        return $version;
    }

    /**
     * Vue "modération" — toutes les entrées, tous statuts confondus, pour
     * l'UI admin (contrairement à findAll/findOne qui ne montrent jamais que
     * le catalogue public "approved").
     *
     * @return array{items: \Illuminate\Support\Collection<int, CatalogEntry>, total: int}
     */
    public function findAllForReview(?string $status, int $page, int $perPage): array
    {
        $query = CatalogEntry::with(['publisherInstance', 'reviewedBy']);

        if ($status !== null) {
            if (!in_array($status, self::VALID_STATUSES, true)) {
                throw ApiException::badRequest(sprintf('Statut invalide : "%s" (attendu : %s).', $status, implode(', ', self::VALID_STATUSES)));
            }
            $query->where('status', $status);
        }

        $total = (clone $query)->count();
        $items = $query->orderBy('createdAt', 'desc')->forPage($page, $perPage)->get();

        return ['items' => $items, 'total' => $total];
    }

    public function findOneForReview(string $id): CatalogEntry
    {
        /** @var CatalogEntry|null $entry */
        $entry = CatalogEntry::with(['publisherInstance', 'reviewedBy', 'versions'])->find($id);
        if ($entry === null) {
            throw ApiException::notFound(sprintf('Item de catalogue "%s" introuvable.', $id));
        }

        return $entry;
    }

    public function approve(string $id, User $reviewer): CatalogEntry
    {
        $entry = $this->findOneForReview($id);
        $entry->status = 'approved';
        $entry->reviewedByUserId = $reviewer->id;
        $entry->reviewedAt = new \DateTimeImmutable();
        $entry->rejectionReason = null;
        $entry->save();
        $entry->setRelation('reviewedBy', $reviewer);

        return $entry;
    }

    public function reject(string $id, User $reviewer, ?string $reason): CatalogEntry
    {
        $entry = $this->findOneForReview($id);
        $entry->status = 'rejected';
        $entry->reviewedByUserId = $reviewer->id;
        $entry->reviewedAt = new \DateTimeImmutable();
        $entry->rejectionReason = $reason;
        $entry->save();
        $entry->setRelation('reviewedBy', $reviewer);

        return $entry;
    }

    private function findOwnedEntry(PublisherInstance $publisher, string $entryId): CatalogEntry
    {
        /** @var CatalogEntry|null $entry */
        $entry = CatalogEntry::find($entryId);
        if ($entry === null || $entry->publisherInstanceId !== $publisher->id) {
            // Même message que "introuvable" — jamais confirmer l'existence d'un item d'un autre éditeur.
            throw ApiException::notFound(sprintf('Item de catalogue "%s" introuvable.', $entryId));
        }

        return $entry;
    }

    /** @param array<string, mixed> $content */
    private function snapshotVersion(CatalogEntry $entry, array $content, string $version, ?string $notes): CatalogEntryVersion
    {
        $last = CatalogEntryVersion::where('catalogEntryId', $entry->id)->orderBy('versionNumber', 'desc')->first();
        $versionNumber = ($last->versionNumber ?? 0) + 1;

        $snapshot = new CatalogEntryVersion([
            'catalogEntryId' => $entry->id,
            'versionNumber' => $versionNumber,
            'version' => $version,
            'content' => $content,
            'notes' => $notes,
        ]);
        $snapshot->save();

        return $snapshot;
    }
}
