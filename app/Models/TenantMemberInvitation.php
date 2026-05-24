<?php

namespace App\Models;

use App\Models\Concerns\HasPrefixedPublicId;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'tenant_id',
    'invited_by_user_id',
    'accepted_user_id',
    'email',
    'role',
    'token_hash',
    'expires_at',
    'accepted_at',
    'revoked_at',
])]
class TenantMemberInvitation extends Model
{
    use HasPrefixedPublicId;

    public const STATUS_ACCEPTED = 'accepted';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_PENDING = 'pending';

    public const STATUS_REVOKED = 'revoked';

    protected static function publicIdPrefix(): string
    {
        return 'inv';
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function acceptedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'accepted_user_id');
    }

    public function isPending(): bool
    {
        return $this->accepted_at === null
            && $this->revoked_at === null
            && $this->expires_at->isFuture();
    }

    public function status(): string
    {
        if ($this->accepted_at !== null) {
            return self::STATUS_ACCEPTED;
        }

        if ($this->revoked_at !== null) {
            return self::STATUS_REVOKED;
        }

        if ($this->expires_at->isPast()) {
            return self::STATUS_EXPIRED;
        }

        return self::STATUS_PENDING;
    }
}
