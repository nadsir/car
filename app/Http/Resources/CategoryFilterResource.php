<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CategoryFilterResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'type' => $this->type,

            'is_filterable' => (bool) $this->pivot?->is_filterable,

            'is_required' => (bool) $this->pivot?->is_required,

            'is_variant_axis' => (bool) $this->pivot?->is_variant_axis,

            'sort_order' => (int) $this->pivot?->sort_order,

            'values' => $this->values->map(function ($value) {
                return [
                    'id' => $value->id,
                    'label' => $value->label,
                    'value' => $value->value,
                    'hex_color' => $value->hex_color,
                ];
            })->values(),
        ];
    }
}
