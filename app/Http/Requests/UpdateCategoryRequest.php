<?php

namespace App\Http\Requests;

use App\Models\Category;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateCategoryRequest extends FormRequest
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
        $categoryId = $this->route('category');

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
                    ->ignore($this->route('category'))
                    ->where(static function ($query) use ($user): void {
                        $query
                            ->where('tenant_id', $user?->tenant_id)
                            ->where('owner_user_id', $user?->getKey());
                    }),
            ],
            'parent_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::notIn([$categoryId]),
                Rule::exists('categories', 'id')->where(static function ($query) use ($user): void {
                    $query
                        ->where('tenant_id', $user?->tenant_id)
                        ->where('owner_user_id', $user?->getKey())
                        ->whereNull('parent_id');
                }),
            ],
            'sort_order' => ['sometimes', 'required', 'integer', 'min:0', 'max:999999'],
        ];
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

            $user = $this->user();

            if (! $user?->tenant_id) {
                return;
            }

            $category = Category::query()
                ->whereKey($this->route('category'))
                ->where('tenant_id', $user->tenant_id)
                ->where('owner_user_id', $user->getKey())
                ->first();

            if (! $category) {
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

        if (array_key_exists('parent_id', $input) && $input['parent_id'] === '') {
            $input['parent_id'] = null;
        }

        $this->replace($input);
    }
}
