<?php

namespace App\Http\Requests;

use App\Support\ScopedPublicIdResolver;
use App\Support\TenantUserContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MemorySpaceRequest extends FormRequest
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
        return [
            'period_key' => ['nullable', 'string', Rule::in([
                'childhood',
                'elementary_school',
                'junior_high',
                'high_school',
                'university',
                'adult',
            ])],
            'category_id' => [
                'nullable',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (
                        ! ScopedPublicIdResolver::isBlankIdentifier($value)
                        && ! ScopedPublicIdResolver::isCategoryIdentifier($value)
                    ) {
                        $fail('The '.$attribute.' field must be a valid category identifier.');
                    }
                },
            ],
            'include_descendants' => ['nullable', 'boolean'],
            'include_secret' => ['nullable', 'boolean'],
        ];
    }

    public function resolvedCategoryFilterId(): ?int
    {
        $value = $this->input('category_id');

        if (ScopedPublicIdResolver::isBlankIdentifier($value)) {
            return null;
        }

        $category = ScopedPublicIdResolver::category(TenantUserContext::fromUser($this->user()), $value);

        return $category === null ? 0 : (int) $category->getKey();
    }

    protected function prepareForValidation(): void
    {
        $input = $this->all();

        foreach (['period_key', 'category_id'] as $field) {
            if (array_key_exists($field, $input) && is_string($input[$field])) {
                $input[$field] = trim($input[$field]);
            }
        }

        foreach (['include_descendants', 'include_secret'] as $field) {
            $this->normalizeBoolean($input, $field);
        }

        foreach (['period_key', 'category_id', 'include_descendants', 'include_secret'] as $nullableField) {
            if (($input[$nullableField] ?? null) === '') {
                $input[$nullableField] = null;
            }
        }

        $this->replace($input);
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function normalizeBoolean(array &$input, string $field): void
    {
        if (! array_key_exists($field, $input)) {
            return;
        }

        if ($input[$field] === '') {
            $input[$field] = null;

            return;
        }

        if (! is_string($input[$field])) {
            return;
        }

        $value = strtolower(trim($input[$field]));

        if (in_array($value, ['1', 'true', 'yes', 'on'], true)) {
            $input[$field] = true;

            return;
        }

        if (in_array($value, ['0', 'false', 'no', 'off'], true)) {
            $input[$field] = false;

            return;
        }

        $input[$field] = trim($input[$field]);
    }
}
