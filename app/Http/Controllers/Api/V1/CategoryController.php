<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\CategoryContextRequest;
use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use App\Support\TenantUserContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as HttpStatus;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class CategoryController extends Controller
{
    public function index(CategoryContextRequest $request): AnonymousResourceCollection
    {
        $context = TenantUserContext::fromUser($request->user());

        $categories = Category::queryForContext($context)
            ->withCount('memories')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return CategoryResource::collection($categories);
    }

    public function store(StoreCategoryRequest $request): JsonResponse
    {
        $context = TenantUserContext::fromUser($request->user());
        $data = $request->validated();

        $category = Category::query()->create([
            'tenant_id' => $context->tenantId(),
            'owner_user_id' => $context->userId(),
            'name' => $data['name'],
            'slug' => $data['slug'],
            'sort_order' => $data['sort_order'] ?? 0,
        ])->loadCount('memories');

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
        $category->fill($request->validated());
        $category->save();
        $category->loadCount('memories');

        return new CategoryResource($category);
    }

    public function destroy(CategoryContextRequest $request, int|string $category): Response
    {
        $category = $this->findCategoryForRequest($request, $category);
        $category->memories()->update(['category_id' => null]);
        $category->delete();

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

        return $model->loadCount('memories');
    }
}
