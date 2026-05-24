<?php

namespace App\Http\Requests;

use App\Models\Category;
use App\Support\ScopedPublicIdResolver;
use App\Support\TenantUserContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class UpdateCategoryRequest extends FormRequest
{
    private bool $routeCategoryResolved = false;

    private ?Category $routeCategory = null;

    public function authorize(): bool
    {
        return $this->user()?->tenant_id !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $user = $this->user();
        $category = $this->routeCategory();

        return [
            'name' => ['sometimes', 'required', 'string', 'min:1', 'max:80'],
            'slug' => [
                'sometimes',
                'required',
                'string',
                'min:1',
                'max:80',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('categories', 'slug')
                    ->ignore($category?->getKey())
                    ->where(static function ($query) use ($user): void {
                        $query
                            ->where('tenant_id', $user?->tenant_id)
                            ->where('owner_user_id', $user?->getKey());
                    }),
            ],
            'parent_id' => [
                'sometimes',
                'nullable',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (ScopedPublicIdResolver::isBlankIdentifier($value)) {
                        return;
                    }

                    if (! ScopedPublicIdResolver::isCategoryIdentifier($value)) {
                        $fail('The '.$attribute.' field must be a valid category identifier.');
                    }
                },
            ],
            'sort_order' => ['sometimes', 'required', 'integer', 'min:0', 'max:999999'],
        ];
    }

    public function resolvedParentId(): ?int
    {
        $value = $this->input('parent_id');

        if (ScopedPublicIdResolver::isBlankIdentifier($value)) {
            return null;
        }

        $parent = $this->parentCategory();

        return $parent === null ? null : (int) $parent->getKey();
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (
                $validator->errors()->has('parent_id')
                || ! $this->has('parent_id')
                || $this->input('parent_id') === null
                || $this->input('parent_id') === ''
            ) {
                return;
            }

            $category = $this->routeCategory();
            $parent = $this->parentCategory();

            if (! $category || ! $parent) {
                $validator->errors()->add('parent_id', 'The selected parent id is invalid.');

                return;
            }

            if ($parent->is($category)) {
                $validator->errors()->add('parent_id', 'The selected parent id is invalid.');

                return;
            }

            if ($parent->parent_id !== null) {
                $validator->errors()->add('parent_id', 'The selected parent id is invalid.');

                return;
            }

            if ($category->children()->exists()) {
                $validator->errors()->add(
                    'parent_id',
                    '子カテゴリを持つカテゴリはサブカテゴリに変更できません。'
                );
            }
        });
    }

    protected function prepareForValidation(): void
    {
        $input = $this->all();

        foreach (['name', 'slug'] as $field) {
            if (array_key_exists($field, $input) && is_string($input[$field])) {
                $input[$field] = trim($input[$field]);
            }
        }

        if (isset($input['slug']) && is_string($input['slug'])) {
            $input['slug'] = strtolower($input['slug']);
        }

        if (array_key_exists('parent_id', $input) && is_string($input['parent_id'])) {
            $input['parent_id'] = trim($input['parent_id']);
        }

        if (array_key_exists('parent_id', $input) && $input['parent_id'] === '') {
            $input['parent_id'] = null;
        }

        $this->replace($input);

        if ($this->user()?->tenant_id !== null && ! $this->routeCategory()) {
            throw new NotFoundHttpException;
        }
    }

    private function routeCategory(): ?Category
    {
        if ($this->routeCategoryResolved) {
            return $this->routeCategory;
        }

        $this->routeCategoryResolved = true;

        $user = $this->user();

        if (! $user?->tenant_id) {
            return null;
        }

        $this->routeCategory = ScopedPublicIdResolver::category(
            TenantUserContext::fromUser($user),
            $this->route('category'),
        );

        return $this->routeCategory;
    }

    private function parentCategory(): ?Category
    {
        if (ScopedPublicIdResolver::isBlankIdentifier($this->input('parent_id'))) {
            return null;
        }

        return ScopedPublicIdResolver::category(
            TenantUserContext::fromUser($this->user()),
            $this->input('parent_id'),
        );
    }
}
