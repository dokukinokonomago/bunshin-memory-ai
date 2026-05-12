<?php

namespace App\Http\Resources;

use App\Models\Memory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Memory
 */
class MemoryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'period_key' => $this->period_key,
            'occurred_on' => $this->occurred_on?->toDateString(),
            'title' => $this->title,
            'body' => $this->body,
            'emotion_label' => $this->emotion_label,
            'emotion_intensity' => $this->emotion_intensity,
            'visibility' => $this->visibility,
            'category' => $this->whenLoaded('category', function (): ?array {
                if (! $this->category) {
                    return null;
                }

                return [
                    'id' => $this->category->id,
                    'name' => $this->category->name,
                ];
            }, null),
            'tags' => $this->whenLoaded(
                'tags',
                fn () => $this->tags->pluck('name')->values()->all(),
                [],
            ),
            'created_at' => $this->created_at?->toAtomString(),
            'updated_at' => $this->updated_at?->toAtomString(),
        ];
    }
}
