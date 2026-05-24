<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CompleteSecretUnlockPasswordRecoveryRequest extends FormRequest
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
            'password' => ['required', 'string', 'min:8', 'max:1024', 'confirmed'],
        ];
    }
}
