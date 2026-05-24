<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ArchiveTenantRequest extends FormRequest
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
            'confirmation' => ['required', 'string'],
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (! $this->has('reason') || $this->input('reason') === null) {
            return;
        }

        $reason = trim((string) $this->input('reason'));

        $this->merge([
            'reason' => $reason === '' ? null : $reason,
        ]);
    }
}
