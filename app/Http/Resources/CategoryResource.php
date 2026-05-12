<?php

namespace App\Http\Resources;

use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Category
 */
class CategoryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'parent_id' => $this->parent_id === null ? null : (int) $this->parent_id,
            'name' => $this->name,
            'slug' => $this->slug,
            'sort_order' => (int) $this->sort_order,
            'memory_count' => (int) ($this->memories_count ?? 0),
            'archived' => false,
            'children' => $this->when(
                $this->relationLoaded('children'),
                fn () => self::collection($this->children),
            ),
            'created_at' => $this->created_at?->toAtomString(),
            'updated_at' => $this->updated_at?->toAtomString(),
        ];
    }
}
