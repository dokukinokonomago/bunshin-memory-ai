<?php

namespace App\Http\Requests;

use App\Models\Memory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListMemoriesRequest extends FormRequest
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
            'q' => ['nullable', 'string', 'max:255'],
            'period_key' => ['nullable', 'string', Rule::in([
                'childhood',
                'elementary_school',
                'junior_high',
                'high_school',
                'university',
                'adult',
            ])],
            'category_id' => ['nullable', 'integer', 'min:1'],
            'visibility' => ['nullable', 'string', Rule::in([
                Memory::VISIBILITY_PRIVATE,
                Memory::VISIBILITY_SECRET,
                Memory::VISIBILITY_SHARED,
            ])],
        ];
    }

    protected function prepareForValidation(): void
    {
        $input = $this->all();

        foreach (['q', 'period_key', 'visibility'] as $field) {
            if (array_key_exists($field, $input) && is_string($input[$field])) {
                $input[$field] = trim($input[$field]);
            }
        }

        foreach (['q', 'period_key', 'visibility', 'category_id'] as $nullableField) {
            if (($input[$nullableField] ?? null) === '') {
                $input[$nullableField] = null;
            }
        }

        $this->replace($input);
    }
}
