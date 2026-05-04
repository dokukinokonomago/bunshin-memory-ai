<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'sort_order' => ['sometimes', 'required', 'integer', 'min:0', 'max:999999'],
        ];
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

        $this->replace($input);
    }
}
