<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ForceSecretUnlockPasswordRotationRequest extends FormRequest
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
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $input = $this->all();

        if (array_key_exists('reason', $input) && is_string($input['reason'])) {
            $reason = trim($input['reason']);
            $input['reason'] = $reason === '' ? null : $reason;
        }

        $this->replace($input);
    }
}
