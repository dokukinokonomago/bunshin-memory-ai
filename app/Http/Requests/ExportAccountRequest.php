<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ExportAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'current_password' => ['required', 'string', 'max:1024'],
            'include_secret' => ['nullable', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $input = $this->all();

        $this->normalizeBoolean($input, 'include_secret');

        if (($input['include_secret'] ?? null) === '') {
            $input['include_secret'] = null;
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
