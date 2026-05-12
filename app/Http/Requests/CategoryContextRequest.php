<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CategoryContextRequest extends FormRequest
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
            'tree' => ['nullable', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $input = $this->all();

        $this->normalizeBoolean($input, 'tree');

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
