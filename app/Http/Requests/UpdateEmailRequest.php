<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateEmailRequest extends FormRequest
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
        /** @var User|null $user */
        $user = $this->user();
        $userId = $user instanceof User ? $user->id : null;

        return [
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($userId),
                Rule::unique('users', 'pending_email')->ignore($userId),
            ],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            /** @var User|null $user */
            $user = $this->user();
            $email = $this->input('email');

            if (! $user instanceof User || ! is_string($email)) {
                return;
            }

            if (hash_equals(Str::lower($user->email), $email)) {
                $validator->errors()->add('email', 'The email must be different from the current email.');
            }
        });
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
