<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Un compte individuel côté équipe marketplace (pas une instance éditrice —
 * voir PublisherInstance) : admin, collaborator ou maintainer, habilité à
 * approuver/rejeter les items soumis au catalogue via l'UI d'admin.
 *
 * @property string $id
 * @property string $name
 * @property string $email
 * @property string $passwordHash
 * @property string $role
 */
class User extends BaseModel
{
    protected $table = 'user';

    const UPDATED_AT = null;

    protected $fillable = ['name', 'email', 'passwordHash', 'role'];

    /** @return array<string, mixed> Ne renvoie jamais passwordHash. */
    public function toApiArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role,
            'createdAt' => self::isoDate($this->createdAt),
        ];
    }
}
