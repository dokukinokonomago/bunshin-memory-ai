<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\MemorySpaceRequest;
use App\Models\Category;
use App\Models\Memory;
use App\Models\SecretUnlockToken;
use App\Support\TenantUserContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;

class MemorySpaceController extends Controller
{
    /**
     * @var array<string, string>
     */
    private const PERIOD_LABELS = [
        'childhood' => '幼少期',
        'elementary_school' => '小学校',
        'junior_high' => '中学校',
        'high_school' => '高校',
        'university' => '大学',
        'adult' => '成人後',
    ];

    public function show(MemorySpaceRequest $request): JsonResponse
    {
        $context = TenantUserContext::fromUser($request->user());
        $filters = $request->validated();
        $categoryIds = $this->categoryFilterIds($context, $request, $filters);
        $secretUnlockToken = $this->validSecretUnlockToken($request, $context);
        $includeUnlockedSecrets = ($filters['include_secret'] ?? false) === true
            && $secretUnlockToken instanceof SecretUnlockToken;

        $memories = Memory::queryForContext($context)
            ->with(['category', 'tags'])
            ->when(
                ! $includeUnlockedSecrets,
                static fn (Builder $query): Builder => $query->visibleByDefault()
            )
            ->when(
                ($filters['period_key'] ?? null) !== null,
                static fn (Builder $query): Builder => $query->where('period_key', $filters['period_key'])
            )
            ->when(
                $categoryIds !== null,
                static fn (Builder $query): Builder => $categoryIds === []
                    ? $query->whereRaw('1 = 0')
                    : $query->whereIn('category_id', $categoryIds)
            )
            ->orderByDesc('occurred_on')
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->get();

        $lockedSecretCount = $includeUnlockedSecrets
            ? 0
            : $this->secretMemoryCount($context, $filters, $categoryIds);

        return response()->json([
            'data' => [
                'categories' => $this->categoryTree(
                    $context,
                    $filters['period_key'] ?? null,
                    $includeUnlockedSecrets,
                ),
                'memories' => $memories
                    ->map(fn (Memory $memory): array => $this->memoryPayload($memory))
                    ->values()
                    ->all(),
                'periods' => $this->periods(),
                'secret' => [
                    'locked' => $lockedSecretCount > 0,
                    'locked_count' => $lockedSecretCount,
                    'unlock_expires_at' => $secretUnlockToken?->expires_at?->toAtomString(),
                ],
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<int, int>|null
     */
    private function categoryFilterIds(
        TenantUserContext $context,
        MemorySpaceRequest $request,
        array $filters
    ): ?array {
        if (($filters['category_id'] ?? null) === null) {
            return null;
        }

        $categoryId = $request->resolvedCategoryFilterId();

        if ($categoryId === null) {
            return null;
        }

        if (($filters['include_descendants'] ?? true) === false) {
            return Category::queryForContext($context)->whereKey($categoryId)->exists()
                ? [$categoryId]
                : [];
        }

        return $this->categoryIdsWithDescendants($context, $categoryId);
    }

    /**
     * @return array<int, int>
     */
    private function categoryIdsWithDescendants(TenantUserContext $context, int $categoryId): array
    {
        $category = Category::queryForContext($context)
            ->whereKey($categoryId)
            ->first(['id']);

        if (! $category) {
            return [];
        }

        $ids = [(int) $category->getKey()];
        $frontier = $ids;

        while ($frontier !== []) {
            $children = Category::queryForContext($context)
                ->whereIn('parent_id', $frontier)
                ->pluck('id')
                ->map(static fn (mixed $id): int => (int) $id)
                ->all();

            $children = array_values(array_diff($children, $ids));

            if ($children === []) {
                break;
            }

            $ids = [...$ids, ...$children];
            $frontier = $children;
        }

        return $ids;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @param  array<int, int>|null  $categoryIds
     */
    private function secretMemoryCount(TenantUserContext $context, array $filters, ?array $categoryIds): int
    {
        return Memory::queryForContext($context)
            ->where('visibility', Memory::VISIBILITY_SECRET)
            ->when(
                ($filters['period_key'] ?? null) !== null,
                static fn (Builder $query): Builder => $query->where('period_key', $filters['period_key'])
            )
            ->when(
                $categoryIds !== null,
                static fn (Builder $query): Builder => $categoryIds === []
                    ? $query->whereRaw('1 = 0')
                    : $query->whereIn('category_id', $categoryIds)
            )
            ->count();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function categoryTree(TenantUserContext $context, ?string $periodKey, bool $includeUnlockedSecrets): array
    {
        $categories = Category::queryForContext($context)
            ->with('parent')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $visibleCounts = $this->memoryCountsByCategory($context, $periodKey, false, $includeUnlockedSecrets);
        $secretCounts = $includeUnlockedSecrets
            ? []
            : $this->memoryCountsByCategory($context, $periodKey, true);
        $childrenByParent = $categories->groupBy(
            static fn (Category $category): int => (int) ($category->parent_id ?? 0)
        );

        return ($childrenByParent->get(0) ?? collect())
            ->map(fn (Category $category): array => $this->categoryNode(
                $category,
                $childrenByParent,
                $visibleCounts,
                $secretCounts,
            )[0])
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, Collection<int, Category>>  $childrenByParent
     * @param  array<int, int>  $visibleCounts
     * @param  array<int, int>  $secretCounts
     * @return array{0: array<string, mixed>, 1: int, 2: int}
     */
    private function categoryNode(
        Category $category,
        Collection $childrenByParent,
        array $visibleCounts,
        array $secretCounts
    ): array {
        $visibleCount = $visibleCounts[(int) $category->getKey()] ?? 0;
        $secretCount = $secretCounts[(int) $category->getKey()] ?? 0;

        $children = ($childrenByParent->get((int) $category->getKey()) ?? collect())
            ->map(function (Category $child) use ($childrenByParent, $visibleCounts, $secretCounts, &$visibleCount, &$secretCount): array {
                [$payload, $childVisibleCount, $childSecretCount] = $this->categoryNode(
                    $child,
                    $childrenByParent,
                    $visibleCounts,
                    $secretCounts,
                );

                $visibleCount += $childVisibleCount;
                $secretCount += $childSecretCount;

                return $payload;
            })
            ->values()
            ->all();

        return [[
            'id' => (int) $category->getKey(),
            'public_id' => $category->public_id,
            'parent_id' => $category->parent_id === null ? null : (int) $category->parent_id,
            'parent_public_id' => $category->parent?->public_id,
            'name' => $category->name,
            'slug' => $category->slug,
            'sort_order' => (int) $category->sort_order,
            'memory_count' => $visibleCount,
            'locked_secret_count' => $secretCount,
            'children' => $children,
        ], $visibleCount, $secretCount];
    }

    /**
     * @return array<int, int>
     */
    private function memoryCountsByCategory(
        TenantUserContext $context,
        ?string $periodKey,
        bool $secret,
        bool $includeUnlockedSecrets = false
    ): array {
        return Memory::queryForContext($context)
            ->whereNotNull('category_id')
            ->when(
                $secret,
                static fn (Builder $query): Builder => $query->where('visibility', Memory::VISIBILITY_SECRET),
                static fn (Builder $query): Builder => $includeUnlockedSecrets
                    ? $query
                    : $query->visibleByDefault(),
            )
            ->when(
                $periodKey !== null,
                static fn (Builder $query): Builder => $query->where('period_key', $periodKey)
            )
            ->selectRaw('category_id, count(*) as aggregate_count')
            ->groupBy('category_id')
            ->pluck('aggregate_count', 'category_id')
            ->mapWithKeys(static fn (mixed $count, mixed $categoryId): array => [
                (int) $categoryId => (int) $count,
            ])
            ->all();
    }

    private function validSecretUnlockToken(
        MemorySpaceRequest $request,
        TenantUserContext $context
    ): ?SecretUnlockToken {
        $unlockToken = SecretUnlockToken::findToken($request->header('X-Secret-Unlock'));

        if (
            ! $unlockToken instanceof SecretUnlockToken
            || $unlockToken->isExpired()
            || (int) $unlockToken->user_id !== $context->userId()
        ) {
            return null;
        }

        $unlockToken->forceFill(['last_used_at' => now()])->save();

        return $unlockToken;
    }

    /**
     * @return array<string, mixed>
     */
    private function memoryPayload(Memory $memory): array
    {
        $metadata = is_array($memory->metadata) ? $memory->metadata : [];

        return [
            'id' => (int) $memory->getKey(),
            'public_id' => $memory->public_id,
            'category_id' => $memory->category_id === null ? null : (int) $memory->category_id,
            'category_public_id' => $memory->category?->public_id,
            'period_key' => $memory->period_key,
            'occurred_on' => $memory->occurred_on?->toDateString(),
            'title' => $memory->title,
            'body' => $memory->body,
            'emotion_label' => $memory->emotion_label,
            'emotion_intensity' => $memory->emotion_intensity,
            'emotion_scores' => $this->numberMap($metadata['emotion_scores'] ?? null),
            'importance_score' => $this->nullableFloat($metadata['importance_score'] ?? null),
            'beliefs' => $this->stringList($metadata['beliefs'] ?? null),
            'chains' => $this->stringList($metadata['chains'] ?? null),
            'tags' => $memory->tags->pluck('name')->values()->all(),
            'visibility' => $memory->visibility,
        ];
    }

    /**
     * @return array<string, int|float>
     */
    private function numberMap(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $numbers = [];

        foreach ($value as $key => $number) {
            if (! is_numeric($number)) {
                continue;
            }

            $numbers[(string) $key] = $number + 0;
        }

        return $numbers;
    }

    private function nullableFloat(mixed $value): ?float
    {
        if (! is_numeric($value)) {
            return null;
        }

        return (float) $value;
    }

    /**
     * @return array<int, string>
     */
    private function stringList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return collect($value)
            ->filter(static fn (mixed $item): bool => is_string($item))
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{key: string, label: string}>
     */
    private function periods(): array
    {
        return collect(self::PERIOD_LABELS)
            ->map(static fn (string $label, string $key): array => [
                'key' => $key,
                'label' => $label,
            ])
            ->values()
            ->all();
    }
}
