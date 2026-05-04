<?php

namespace App\Http\Requests;

use App\Models\Memory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMemoryRequest extends FormRequest
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
        $user = $this->user();

        return [
            'body' => ['required', 'string', 'min:1'],
            'period_key' => ['nullable', 'string', Rule::in([
                'childhood',
                'elementary_school',
                'junior_high',
                'high_school',
                'university',
                'adult',
            ])],
            'occurred_on' => ['nullable', 'date_format:Y-m-d'],
            'title' => ['nullable', 'string', 'max:255'],
            'emotion_label' => ['nullable', 'string', 'max:40'],
            'emotion_intensity' => ['nullable', 'integer', 'min:1', 'max:5'],
            'visibility' => ['required', 'string', Rule::in([
                Memory::VISIBILITY_PRIVATE,
                Memory::VISIBILITY_SECRET,
                Memory::VISIBILITY_SHARED,
            ])],
            'category_id' => [
                'nullable',
                'integer',
                Rule::exists('categories', 'id')->where(static function ($query) use ($user): void {
                    $query
                        ->where('tenant_id', $user?->tenant_id)
                        ->where('owner_user_id', $user?->getKey());
                }),
            ],
            'tags' => ['nullable', 'array', 'max:20'],
            'tags.*' => ['required', 'string', 'min:1', 'max:40', 'distinct:strict'],
            'metadata' => ['nullable', 'array'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $input = $this->all();

        foreach (['body', 'period_key', 'occurred_on', 'title', 'emotion_label', 'visibility'] as $field) {
            if (array_key_exists($field, $input) && is_string($input[$field])) {
                $input[$field] = trim($input[$field]);
            }
        }

        foreach (['period_key', 'occurred_on', 'title', 'emotion_label'] as $nullableField) {
            if (($input[$nullableField] ?? null) === '') {
                $input[$nullableField] = null;
            }
        }

        if (isset($input['tags']) && is_array($input['tags'])) {
            $input['tags'] = array_map(
                static fn (mixed $tag): mixed => is_string($tag) ? trim($tag) : $tag,
                $input['tags'],
            );
        }

        $this->replace($input);
    }
}
