<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * @mixin Model
 */
trait HasPrefixedPublicId
{
    public static function bootHasPrefixedPublicId(): void
    {
        static::creating(static function (Model $model): void {
            $publicId = $model->getAttribute('public_id');

            if (is_string($publicId) && $publicId !== '') {
                return;
            }

            $model->setAttribute('public_id', static::newUniquePublicId());
        });
    }

    public static function newPublicId(): string
    {
        return static::publicIdPrefix().'_'.(string) Str::ulid();
    }

    public static function newUniquePublicId(): string
    {
        do {
            $publicId = static::newPublicId();
        } while (static::query()->where('public_id', $publicId)->exists());

        return $publicId;
    }

    public function ensurePublicId(): void
    {
        $publicId = $this->getAttribute('public_id');

        if (is_string($publicId) && $publicId !== '') {
            return;
        }

        $this->forceFill([
            'public_id' => static::newUniquePublicId(),
        ])->saveQuietly();
    }

    abstract protected static function publicIdPrefix(): string;
}
