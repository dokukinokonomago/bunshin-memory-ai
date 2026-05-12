<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable([
    'name',
    'token',
    'abilities',
    'last_used_at',
    'expires_at',
])]
#[Hidden(['token'])]
class PersonalAccessToken extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'abilities' => 'array',
            'last_used_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function tokenable(): MorphTo
    {
        return $this->morphTo();
    }

    public static function findToken(?string $plainTextToken): ?self
    {
        if ($plainTextToken === null || $plainTextToken === '') {
            return null;
        }

        if (! str_contains($plainTextToken, '|')) {
            return static::query()
                ->where('token', hash('sha256', $plainTextToken))
                ->first();
        }

        [$id, $token] = explode('|', $plainTextToken, 2);

        if ($id === '' || $token === '' || ! ctype_digit($id)) {
            return null;
        }

        $accessToken = static::query()->find($id);

        if (! $accessToken instanceof self) {
            return null;
        }

        return hash_equals($accessToken->token, hash('sha256', $token))
            ? $accessToken
            : null;
    }

    public function isExpired(): bool
    {
        return $this->expires_at instanceof CarbonInterface
            && $this->expires_at->isPast();
    }
}
