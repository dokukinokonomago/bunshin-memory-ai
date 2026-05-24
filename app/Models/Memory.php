<?php

namespace App\Models;

use App\Models\Concerns\HasPrefixedPublicId;
use App\Support\ScopedPublicIdResolver;
use App\Support\TenantUserContext;
use Database\Factories\MemoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'tenant_id',
    'owner_user_id',
    'category_id',
    'period_key',
    'occurred_on',
    'title',
    'body',
    'emotion_label',
    'emotion_intensity',
    'visibility',
    'source',
    'metadata',
])]
class Memory extends Model
{
    /** @use HasFactory<MemoryFactory> */
    use HasFactory, HasPrefixedPublicId, SoftDeletes;

    public const VISIBILITY_PRIVATE = 'private';

    public const VISIBILITY_SECRET = 'secret';

    public const VISIBILITY_SHARED = 'shared';

    protected static function publicIdPrefix(): string
    {
        return 'mem';
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'occurred_on' => 'date',
            'emotion_intensity' => 'integer',
            'metadata' => 'array',
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
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return BelongsToMany<Tag, $this>
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class)->withTimestamps();
    }

    /**
     * @param  Builder<Memory>  $query
     */
    public function scopeForTenant(Builder $query, Tenant|int $tenant): void
    {
        $query->where('tenant_id', $tenant instanceof Tenant ? $tenant->id : $tenant);
    }

    /**
     * @param  Builder<Memory>  $query
     */
    public function scopeForOwner(Builder $query, User|int $owner): void
    {
        $query->where('owner_user_id', $owner instanceof User ? $owner->id : $owner);
    }

    /**
     * @param  Builder<Memory>  $query
     */
    public function scopeForContext(Builder $query, TenantUserContext $context): void
    {
        $query
            ->where('tenant_id', $context->tenantId())
            ->where('owner_user_id', $context->userId());
    }

    /**
     * @param  Builder<Memory>  $query
     */
    public function scopeVisibleByDefault(Builder $query): void
    {
        $query->where('visibility', '!=', self::VISIBILITY_SECRET);
    }

    /**
     * @return Builder<Memory>
     */
    public static function queryForContext(TenantUserContext $context): Builder
    {
        return static::query()->forContext($context);
    }

    public static function findForContext(TenantUserContext $context, int|string $id): ?self
    {
        return ScopedPublicIdResolver::memory($context, $id);
    }
}
