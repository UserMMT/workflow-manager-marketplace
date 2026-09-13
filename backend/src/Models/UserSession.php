<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Session de connexion d'un User — jeton opaque (voir UserAuthService), la
 * valeur brute n'est jamais stockée, seulement son hash. Même logique que la
 * clé API de PublisherInstance, appliquée à un compte individuel.
 *
 * @property string $id
 * @property string $userId
 * @property string $tokenHash
 * @property \Carbon\Carbon $expiresAt
 */
class UserSession extends BaseModel
{
    protected $table = 'user_session';

    const UPDATED_AT = null;

    protected $fillable = ['userId', 'tokenHash', 'expiresAt'];

    protected $casts = [
        'expiresAt' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'userId', 'id');
    }
}
