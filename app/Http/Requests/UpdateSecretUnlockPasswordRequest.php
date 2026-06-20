<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSecretUnlockPasswordRequest extends FormRequest
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
            'account_password' => ['required', 'string', 'max:1024'],
            'current_password' => [
                Rule::requiredIf(fn (): bool => (bool) $this->user()?->hasSecretUnlockPassword()),
                'string',
                'max:1024',
            ],
            'password' => ['required', 'string', 'min:8', 'max:1024', 'confirmed'],
        ];
    }
}
