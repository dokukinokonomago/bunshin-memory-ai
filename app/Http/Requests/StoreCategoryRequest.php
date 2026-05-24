<?php

namespace App\Http\Requests;

use App\Support\ScopedPublicIdResolver;
use App\Support\TenantUserContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCategoryRequest extends FormRequest
{
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

        return [
            'name' => ['required', 'string', 'min:1', 'max:80'],
            'slug' => [
                'required',
                'string',
                'min:1',
                'max:80',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('categories', 'slug')->where(static function ($query) use ($user): void {
                    $query
                        ->where('tenant_id', $user?->tenant_id)
                        ->where('owner_user_id', $user?->getKey());
                }),
            ],
            'parent_id' => [
                'nullable',
                function (string $attribute, mixed $value, \Closure $fail) use ($user): void {
                    if (ScopedPublicIdResolver::isBlankIdentifier($value)) {
                        return;
                    }

                    if (! ScopedPublicIdResolver::isCategoryIdentifier($value)) {
                        $fail('The '.$attribute.' field must be a valid category identifier.');

                        return;
                    }

                    if (! $user?->tenant_id) {
                        return;
                    }

                    $parent = ScopedPublicIdResolver::category(TenantUserContext::fromUser($user), $value);

                    if (! $parent || $parent->parent_id !== null) {
                        $fail('The selected '.$attribute.' is invalid.');
                    }
                },
            ],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999999'],
        ];
    }

    public function resolvedParentId(): ?int
    {
        $value = $this->input('parent_id');

        if (ScopedPublicIdResolver::isBlankIdentifier($value)) {
            return null;
        }

        $parent = ScopedPublicIdResolver::category(TenantUserContext::fromUser($this->user()), $value);

        return $parent === null ? null : (int) $parent->getKey();
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
    }
}
