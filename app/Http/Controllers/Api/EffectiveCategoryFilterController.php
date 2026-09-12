<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryFilterResource;
use App\Models\Category;
use App\Services\EffectiveCategoryAttributesResolver;

class EffectiveCategoryFilterController extends Controller
{
    public function __construct(
        private readonly EffectiveCategoryAttributesResolver $effectiveAttributes
    ) {
    }

    public function index(string $slug)
    {
        $category = Category::query()
            ->where('slug', $slug)
            ->where('is_active', true)
            ->firstOrFail();

        $filters = $this->effectiveAttributes
            ->for($category)
            ->filter(fn ($attribute) => $attribute->pivot->is_filterable)
            ->values();

        return CategoryFilterResource::collection($filters);
    }
}
