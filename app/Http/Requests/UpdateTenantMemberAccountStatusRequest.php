<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdateTenantMemberAccountStatusRequest extends FormRequest
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
            'account_status' => ['required', 'string', Rule::in(User::ACCOUNT_STATUSES)],
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $input = $this->all();

        if (array_key_exists('account_status', $input) && is_string($input['account_status'])) {
            $input['account_status'] = Str::lower(trim($input['account_status']));
        }

        if (array_key_exists('reason', $input) && is_string($input['reason'])) {
            $reason = trim($input['reason']);
            $input['reason'] = $reason === '' ? null : $reason;
        }

        $this->replace($input);
    }
}
