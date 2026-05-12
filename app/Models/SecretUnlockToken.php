<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'token',
    'last_used_at',
    'expires_at',
])]
#[Hidden(['token'])]
class SecretUnlockToken extends Model
{
    public const TTL_MINUTES = 15;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_used_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
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

        $unlockToken = static::query()->find($id);

        if (! $unlockToken instanceof self) {
            return null;
        }

        return hash_equals($unlockToken->token, hash('sha256', $token))
            ? $unlockToken
            : null;
    }

    public function isExpired(): bool
    {
        return $this->expires_at instanceof CarbonInterface
            && $this->expires_at->isPast();
    }
}
