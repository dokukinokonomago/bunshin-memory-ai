<?php

namespace App\Http\Requests;

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
            'category_id' => ['nullable', 'integer', 'min:1'],
            'include_descendants' => ['nullable', 'boolean'],
            'include_secret' => ['nullable', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $input = $this->all();

        if (array_key_exists('period_key', $input) && is_string($input['period_key'])) {
            $input['period_key'] = trim($input['period_key']);
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
