<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Ramsey\Uuid\Uuid;

/** UUID PK générée côté client + timestamps ISO — même convention que back_php/src/Models/BaseModel.php. */
abstract class BaseModel extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = true;

    const CREATED_AT = 'createdAt';
    const UPDATED_AT = null;

    protected $dateFormat = 'Y-m-d H:i:s';

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (BaseModel $model): void {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = Uuid::uuid4()->toString();
            }
        });
    }

    protected function serializeDate(\DateTimeInterface $date): string
    {
        return $date->format('Y-m-d\TH:i:s.v\Z');
    }

    public static function isoDate(?\DateTimeInterface $date): ?string
    {
        return $date?->format('Y-m-d\TH:i:s.v\Z');
    }
}
