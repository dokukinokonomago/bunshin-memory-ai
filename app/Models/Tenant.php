<?php

namespace App\Models;

use App\Models\Concerns\HasPrefixedPublicId;
use Database\Factories\TenantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'name',
    'slug',
    'plan_key',
    'subscription_status',
    'trial_ends_at',
    'subscription_ends_at',
    'archived_at',
    'archived_by_user_id',
    'archive_reason',
    'deletion_requested_at',
    'scheduled_deletion_at',
    'purged_at',
    'billing_provider',
    'billing_customer_id',
    'billing_subscription_id',
    'billing_price_id',
    'billing_cancel_at_period_end',
    'billing_last_synced_at',
])]
class Tenant extends Model
{
    /** @use HasFactory<TenantFactory> */
    use HasFactory, HasPrefixedPublicId;

    public const PLAN_FREE = 'free';

    public const PLAN_PRO = 'pro';

    public const SUBSCRIPTION_STATUS_ACTIVE = 'active';

    public const SUBSCRIPTION_STATUS_TRIALING = 'trialing';

    public const SUBSCRIPTION_STATUS_PAST_DUE = 'past_due';

    public const SUBSCRIPTION_STATUS_CANCELED = 'canceled';

    public const SUBSCRIPTION_STATUS_INCOMPLETE = 'incomplete';

    /**
     * @var list<string>
     */
    public const ACTIVE_SUBSCRIPTION_STATUSES = [
        self::SUBSCRIPTION_STATUS_ACTIVE,
        self::SUBSCRIPTION_STATUS_TRIALING,
    ];

    protected static function publicIdPrefix(): string
    {
        return 'ten';
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'trial_ends_at' => 'datetime',
            'subscription_ends_at' => 'datetime',
            'archived_at' => 'datetime',
            'deletion_requested_at' => 'datetime',
            'scheduled_deletion_at' => 'datetime',
            'purged_at' => 'datetime',
            'billing_cancel_at_period_end' => 'boolean',
            'billing_last_synced_at' => 'datetime',
        ];
    }

    public function hasActivePlan(): bool
    {
        if ($this->isArchived()) {
            return false;
        }

        $status = $this->subscription_status ?? self::SUBSCRIPTION_STATUS_ACTIVE;

        if (! in_array($status, self::ACTIVE_SUBSCRIPTION_STATUSES, true)) {
            return false;
        }

        if ($status === self::SUBSCRIPTION_STATUS_TRIALING
            && $this->trial_ends_at !== null
            && $this->trial_ends_at->isPast()) {
            return false;
        }

        return $this->subscription_ends_at === null || $this->subscription_ends_at->isFuture();
    }

    public function memoryQuotaLimit(): ?int
    {
        return $this->planLimit('memories');
    }

    public function categoryQuotaLimit(): ?int
    {
        return $this->planLimit('categories');
    }

    public function planLimit(string $resource): ?int
    {
        $plans = config('bunshin.plans', []);

        if (! is_array($plans)) {
            return null;
        }

        $planKey = is_string($this->plan_key) ? $this->plan_key : self::PLAN_FREE;
        $plan = $plans[$planKey] ?? $plans[self::PLAN_FREE] ?? [];
        $limits = is_array($plan) && is_array($plan['limits'] ?? null) ? $plan['limits'] : [];
        $limit = $limits[$resource] ?? null;

        if ($limit === null) {
            return null;
        }

        return max(0, (int) $limit);
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function archivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'archived_by_user_id');
    }

    /**
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * @return HasMany<TenantMemberInvitation, $this>
     */
    public function memberInvitations(): HasMany
    {
        return $this->hasMany(TenantMemberInvitation::class);
    }

    /**
     * @return HasMany<SecurityEvent, $this>
     */
    public function securityEvents(): HasMany
    {
        return $this->hasMany(SecurityEvent::class);
    }

    /**
     * @return HasMany<BillingWebhookEvent, $this>
     */
    public function billingWebhookEvents(): HasMany
    {
        return $this->hasMany(BillingWebhookEvent::class);
    }

    /**
     * @return HasMany<Memory, $this>
     */
    public function memories(): HasMany
    {
        return $this->hasMany(Memory::class);
    }

    /**
     * @return HasMany<Category, $this>
     */
    public function categories(): HasMany
    {
        return $this->hasMany(Category::class);
    }

    /**
     * @return HasMany<Tag, $this>
     */
    public function tags(): HasMany
    {
        return $this->hasMany(Tag::class);
    }
}
