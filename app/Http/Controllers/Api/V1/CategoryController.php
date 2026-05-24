<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\CategoryContextRequest;
use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use App\Models\SecurityEvent;
use App\Support\SecurityEventLogger;
use App\Support\TenantQuotaGuard;
use App\Support\TenantUserContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as HttpStatus;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class CategoryController extends Controller
{
    public function __construct(private readonly SecurityEventLogger $securityEvents) {}

    public function index(CategoryContextRequest $request): AnonymousResourceCollection
    {
        $context = TenantUserContext::fromUser($request->user());
        $filters = $request->validated();

        if (($filters['tree'] ?? false) === true) {
            $categories = Category::queryForContext($context)
                ->whereNull('parent_id')
                ->with([
                    'children' => static function ($query): void {
                        $query
                            ->with([
                                'children' => static function ($query): void {
                                    $query
                                        ->with('parent')
                                        ->withCount('memories')
                                        ->orderBy('sort_order')
                                        ->orderBy('name');
                                },
                            ])
                            ->with('parent')
                            ->withCount('memories')
                            ->orderBy('sort_order')
                            ->orderBy('name');
                    },
                ])
                ->withCount('memories')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get();

            return CategoryResource::collection($categories);
        }

        $categories = Category::queryForContext($context)
            ->with('parent')
            ->withCount('memories')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return CategoryResource::collection($categories);
    }

    public function store(StoreCategoryRequest $request): JsonResponse
    {
        $context = TenantUserContext::fromUser($request->user());
        TenantQuotaGuard::forTenant($context->tenant())->ensureCanCreateCategory();

        $data = $request->validated();

        $category = Category::query()->create([
            'tenant_id' => $context->tenantId(),
            'owner_user_id' => $context->userId(),
            'parent_id' => $request->resolvedParentId(),
            'name' => $data['name'],
            'slug' => $data['slug'],
            'sort_order' => $data['sort_order'] ?? 0,
        ])->loadCount('memories');
        $category->load('parent');

        $this->securityEvents->log(
            request: $request,
            eventType: SecurityEvent::TYPE_CATEGORY_CREATE,
            outcome: SecurityEvent::OUTCOME_SUCCESS,
            tenant: $context->tenant(),
            user: $context->user(),
            metadata: [
                'resource_type' => 'category',
                'resource_public_id' => $category->public_id,
                'parent_public_id' => $category->parent?->public_id,
            ],
        );

        return (new CategoryResource($category))
            ->response()
            ->setStatusCode(HttpStatus::HTTP_CREATED);
    }

    public function show(CategoryContextRequest $request, int|string $category): CategoryResource
    {
        return new CategoryResource($this->findCategoryForRequest($request, $category));
    }

    public function update(UpdateCategoryRequest $request, int|string $category): CategoryResource
    {
        $category = $this->findCategoryForRequest($request, $category);
        $data = $request->validated();
        $changedFields = array_keys($data);

        if (array_key_exists('parent_id', $data)) {
            $data['parent_id'] = $request->resolvedParentId();
        }

        $category->fill($data);
        $category->save();
        $category->load('parent');
        $category->loadCount('memories');

        $context = TenantUserContext::fromUser($request->user());

        $this->securityEvents->log(
            request: $request,
            eventType: SecurityEvent::TYPE_CATEGORY_UPDATE,
            outcome: SecurityEvent::OUTCOME_SUCCESS,
            tenant: $context->tenant(),
            user: $context->user(),
            metadata: [
                'resource_type' => 'category',
                'resource_public_id' => $category->public_id,
                'parent_public_id' => $category->parent?->public_id,
                'changed_fields' => $changedFields,
            ],
        );

        return new CategoryResource($category);
    }

    public function destroy(CategoryContextRequest $request, int|string $category): JsonResponse|Response
    {
        $category = $this->findCategoryForRequest($request, $category);

        if ($category->children()->exists()) {
            return response()->json([
                'message' => '子カテゴリを持つカテゴリは削除できません。',
                'errors' => [
                    'children' => ['子カテゴリを移動または削除してから、カテゴリを削除してください。'],
                ],
            ], HttpStatus::HTTP_UNPROCESSABLE_ENTITY);
        }

        $context = TenantUserContext::fromUser($request->user());
        $metadata = [
            'resource_type' => 'category',
            'resource_public_id' => $category->public_id,
            'parent_public_id' => $category->parent?->public_id,
            'affected_memory_count' => $category->memories()->count(),
        ];

        $category->memories()->update(['category_id' => null]);
        $category->delete();

        $this->securityEvents->log(
            request: $request,
            eventType: SecurityEvent::TYPE_CATEGORY_DELETE,
            outcome: SecurityEvent::OUTCOME_SUCCESS,
            tenant: $context->tenant(),
            user: $context->user(),
            metadata: $metadata,
        );

        return response()->noContent();
    }

    private function findCategoryForRequest(
        CategoryContextRequest|UpdateCategoryRequest $request,
        int|string $category
    ): Category {
        $context = TenantUserContext::fromUser($request->user());
        $model = Category::findForContext($context, $category);

        if (! $model) {
            throw new NotFoundHttpException;
        }

        return $model->load('parent')->loadCount('memories');
    }
}
