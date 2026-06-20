<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class ResetPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email:rfc', 'max:255'],
            'token' => ['required', 'string', 'max:2048'],
            'password' => ['required', 'string', 'min:8', 'max:1024', 'confirmed'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $input = $this->all();

        if (array_key_exists('email', $input) && is_string($input['email'])) {
            $input['email'] = Str::lower(trim($input['email']));
        }

        $this->replace($input);
    }
}
