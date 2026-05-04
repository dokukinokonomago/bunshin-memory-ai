<?php

namespace App\Models;

use App\Support\TenantUserContext;
use Database\Factories\TagFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['tenant_id', 'name', 'normalized_name'])]
class Tag extends Model
{
    /** @use HasFactory<TagFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * @return BelongsToMany<Memory, $this>
     */
    public function memories(): BelongsToMany
    {
        return $this->belongsToMany(Memory::class)->withTimestamps();
    }

    /**
     * @param  Builder<Tag>  $query
     */
    public function scopeForTenant(Builder $query, Tenant|int $tenant): void
    {
        $query->where('tenant_id', $tenant instanceof Tenant ? $tenant->id : $tenant);
    }

    /**
     * @param  Builder<Tag>  $query
     */
    public function scopeForContext(Builder $query, TenantUserContext $context): void
    {
        $query->where('tenant_id', $context->tenantId());
    }

    /**
     * @return Builder<Tag>
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
