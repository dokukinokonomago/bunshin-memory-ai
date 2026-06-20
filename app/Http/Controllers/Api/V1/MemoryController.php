<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ListMemoriesRequest;
use App\Http\Requests\MemoryContextRequest;
use App\Http\Requests\StoreMemoryRequest;
use App\Http\Requests\UpdateMemoryRequest;
use App\Http\Resources\MemoryResource;
use App\Models\Category;
use App\Models\Memory;
use App\Models\SecurityEvent;
use App\Models\Tag;
use App\Support\NormalizedTagName;
use App\Support\SecurityEventLogger;
use App\Support\TagNameNormalizer;
use App\Support\TenantQuotaGuard;
use App\Support\TenantUserContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response as HttpStatus;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class MemoryController extends Controller
{
    public function __construct(private readonly SecurityEventLogger $securityEvents) {}

    public function index(ListMemoriesRequest $request): AnonymousResourceCollection
    {
        $context = TenantUserContext::fromUser($request->user());
        $filters = $request->validated();

        $query = Memory::queryForContext($context)
            ->with(['category', 'tags']);
        $categoryFilterId = $request->resolvedCategoryFilterId();

        $this->applyVisibilityFilter($query, $filters['visibility'] ?? null);

        if (($filters['period_key'] ?? null) !== null) {
            $query->where('period_key', $filters['period_key']);
        }

        if ($categoryFilterId !== null) {
            if (($filters['include_descendants'] ?? false) === true) {
                $categoryIds = $this->categoryIdsWithDescendants($context, $categoryFilterId);

                if ($categoryIds === []) {
                    $query->whereRaw('1 = 0');
                } else {
                    $query->whereIn('category_id', $categoryIds);
                }
            } else {
                $query->where('category_id', $categoryFilterId);
            }
        }

        if (($filters['q'] ?? null) !== null) {
            $this->applySearchFilter($query, $filters['q']);
        }

        $memories = $query
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->get();

        return MemoryResource::collection($memories);
    }

    public function store(StoreMemoryRequest $request): JsonResponse
    {
        $context = TenantUserContext::fromUser($request->user());
        TenantQuotaGuard::forTenant($context->tenant())->ensureCanCreateMemory();

        $data = $request->validated();
        $tagNames = $data['tags'] ?? [];
        $categoryId = $request->resolvedCategoryId();

        unset($data['tags']);

        $memory = DB::transaction(function () use ($context, $data, $tagNames, $categoryId): Memory {
            $memory = Memory::query()->create([
                'tenant_id' => $context->tenantId(),
                'owner_user_id' => $context->userId(),
                'category_id' => $categoryId,
                'period_key' => $data['period_key'] ?? null,
                'occurred_on' => $data['occurred_on'] ?? null,
                'title' => $data['title'] ?? null,
                'body' => $data['body'],
                'emotion_label' => $data['emotion_label'] ?? null,
                'emotion_intensity' => $data['emotion_intensity'] ?? null,
                'visibility' => $data['visibility'],
                'source' => 'manual',
                'metadata' => $data['metadata'] ?? null,
            ]);

            $this->syncTags($memory, $context, $tagNames);

            return $memory->load(['category', 'tags']);
        });

        $this->securityEvents->log(
            request: $request,
            eventType: SecurityEvent::TYPE_MEMORY_CREATE,
            outcome: SecurityEvent::OUTCOME_SUCCESS,
            tenant: $context->tenant(),
            user: $context->user(),
            metadata: [
                'resource_type' => 'memory',
                'resource_public_id' => $memory->public_id,
                'visibility' => $memory->visibility,
                'category_public_id' => $memory->category?->public_id,
                'tag_count' => $memory->tags->count(),
            ],
        );

        return (new MemoryResource($memory))
            ->response()
            ->setStatusCode(HttpStatus::HTTP_CREATED);
    }

    public function show(MemoryContextRequest $request, int|string $memory): MemoryResource
    {
        return new MemoryResource($this->findMemoryForRequest($request, $memory));
    }

    public function update(UpdateMemoryRequest $request, int|string $memory): MemoryResource
    {
        $context = TenantUserContext::fromUser($request->user());
        $memory = $this->findMemoryForRequest($request, $memory);
        $data = $request->validated();
        $hasTags = array_key_exists('tags', $data);
        $changedFields = array_keys($data);
        $tagNames = $data['tags'] ?? [];

        unset($data['tags']);

        if (array_key_exists('category_id', $data)) {
            $data['category_id'] = $request->resolvedCategoryId();
        }

        $memory = DB::transaction(function () use ($context, $memory, $data, $hasTags, $tagNames): Memory {
            $memory->fill($data);
            $memory->save();

            if ($hasTags) {
                $this->syncTags($memory, $context, $tagNames ?? []);
                $memory->touch();
            }

            return $memory->load(['category', 'tags']);
        });

        $this->securityEvents->log(
            request: $request,
            eventType: SecurityEvent::TYPE_MEMORY_UPDATE,
            outcome: SecurityEvent::OUTCOME_SUCCESS,
            tenant: $context->tenant(),
            user: $context->user(),
            metadata: [
                'resource_type' => 'memory',
                'resource_public_id' => $memory->public_id,
                'visibility' => $memory->visibility,
                'category_public_id' => $memory->category?->public_id,
                'tag_count' => $memory->tags->count(),
                'changed_fields' => $changedFields,
            ],
        );

        return new MemoryResource($memory);
    }

    public function destroy(MemoryContextRequest $request, int|string $memory): Response
    {
        $context = TenantUserContext::fromUser($request->user());
        $memory = $this->findMemoryForRequest($request, $memory);
        $metadata = [
            'resource_type' => 'memory',
            'resource_public_id' => $memory->public_id,
            'visibility' => $memory->visibility,
            'category_public_id' => $memory->category?->public_id,
            'tag_count' => $memory->tags->count(),
        ];

        DB::transaction(static function () use ($memory): void {
            $memory->tags()->detach();
            $memory->delete();
        });

        $this->securityEvents->log(
            request: $request,
            eventType: SecurityEvent::TYPE_MEMORY_DELETE,
            outcome: SecurityEvent::OUTCOME_SUCCESS,
            tenant: $context->tenant(),
            user: $context->user(),
            metadata: $metadata,
        );

        return response()->noContent();
    }

    /**
     * @param  Builder<Memory>  $query
     */
    private function applyVisibilityFilter(Builder $query, ?string $visibility): void
    {
        if ($visibility === null) {
            $query->visibleByDefault();

            return;
        }

        $query->where('visibility', $visibility);
    }

    /**
     * @param  Builder<Memory>  $query
     */
    private function applySearchFilter(Builder $query, string $keyword): void
    {
        $like = '%'.$keyword.'%';

        $query->where(static function (Builder $query) use ($like): void {
            $query
                ->where('title', 'like', $like)
                ->orWhere('body', 'like', $like)
                ->orWhereHas('tags', static function (Builder $query) use ($like): void {
                    $query
                        ->where('name', 'like', $like)
                        ->orWhere('normalized_name', 'like', $like);
                });
        });
    }

    private function findMemoryForRequest(Request $request, int|string $memory): Memory
    {
        $context = TenantUserContext::fromUser($request->user());
        $model = Memory::findForContext($context, $memory);

        if (! $model) {
            throw new NotFoundHttpException;
        }

        return $model->load(['category', 'tags']);
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
     * @param  array<int, string>  $tagNames
     */
    private function syncTags(Memory $memory, TenantUserContext $context, array $tagNames): void
    {
        $tagIds = collect($tagNames)
            ->map(static fn (string $tagName) => TagNameNormalizer::normalize($tagName))
            ->unique(static fn (NormalizedTagName $tagName): string => $tagName->normalizedName)
            ->values()
            ->map(static function (NormalizedTagName $tagName) use ($context): int {
                $tag = Tag::query()->firstOrCreate([
                    'tenant_id' => $context->tenantId(),
                    'normalized_name' => $tagName->normalizedName,
                ], [
                    'name' => $tagName->name,
                ]);

                return (int) $tag->getKey();
            })
            ->all();

        $memory->tags()->sync($tagIds);
    }
}
