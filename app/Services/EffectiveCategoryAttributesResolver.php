<?php

namespace App\Services;

use App\Models\Attribute;
use App\Models\Category;
use Illuminate\Support\Collection;
use LogicException;

class EffectiveCategoryAttributesResolver
{
    /**
     * Resolve direct and inherited attributes from root to the selected category.
     * A direct configuration on a child replaces the same attribute from an ancestor.
     *
     * @return Collection<int, Attribute>
     */
    public function for(Category $category): Collection
    {
        $categoryIds = $this->ancestorPathIds($category);

        $categories = Category::query()
            ->whereIn('id', $categoryIds)
            ->with([
                'attributes.values' => function ($query) {
                    $query->orderBy('sort_order');
                },
            ])
            ->get()
            ->keyBy('id');

        $effectiveAttributes = collect();

        foreach ($categoryIds as $categoryId) {
            $pathCategory = $categories->get($categoryId);

            if ($pathCategory === null) {
                continue;
            }

            foreach ($pathCategory->attributes as $attribute) {
                $effectiveAttributes->put($attribute->id, $attribute);
            }
        }

        return $effectiveAttributes
            ->sortBy(fn (Attribute $attribute) => $attribute->pivot->sort_order)
            ->values();
    }

    /**
     * @return list<int>
     */
    private function ancestorPathIds(Category $category): array
    {
        $parentIds = Category::query()->pluck('parent_id', 'id');
        $path = [];
        $seen = [];
        $currentId = $category->id;

        while ($currentId !== null) {
            if (isset($seen[$currentId])) {
                throw new LogicException('Category hierarchy contains a cycle.');
            }

            $seen[$currentId] = true;
            $path[] = $currentId;
            $currentId = $parentIds->get($currentId);
        }

        return array_reverse($path);
    }
}
