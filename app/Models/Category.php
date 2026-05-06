<?php

namespace App\Models;

use App\Support\TenantUserContext;
use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['tenant_id', 'owner_user_id', 'parent_id', 'name', 'slug', 'sort_order'])]
class Category extends Model
{
    /** @use HasFactory<CategoryFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
            'owner_user_id' => 'integer',
            'parent_id' => 'integer',
            'sort_order' => 'integer',
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
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<Category, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /**
     * @return HasMany<Memory, $this>
     */
    public function memories(): HasMany
    {
        return $this->hasMany(Memory::class);
    }

    /**
     * @param  Builder<Category>  $query
     */
    public function scopeForTenant(Builder $query, Tenant|int $tenant): void
    {
        $query->where('tenant_id', $tenant instanceof Tenant ? $tenant->id : $tenant);
    }

    /**
     * @param  Builder<Category>  $query
     */
    public function scopeForOwner(Builder $query, User|int $owner): void
    {
        $query->where('owner_user_id', $owner instanceof User ? $owner->id : $owner);
    }

    /**
     * @param  Builder<Category>  $query
     */
    public function scopeForContext(Builder $query, TenantUserContext $context): void
    {
        $query
            ->where('tenant_id', $context->tenantId())
            ->where('owner_user_id', $context->userId());
    }

    /**
     * @return Builder<Category>
     */
    public static function queryForContext(TenantUserContext $context): Builder
    {
        return static::query()->forContext($context);
    }

    public static function findForContext(TenantUserContext $context, int|string $id): ?self
    {
        return static::queryForContext($context)->whereKey($id)->first();
    }
}
