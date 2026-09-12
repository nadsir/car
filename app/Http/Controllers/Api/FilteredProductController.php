<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\Category;
use App\Models\Product;
use App\Services\CategoryTreeService;
use App\Services\EffectiveCategoryAttributesResolver;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class FilteredProductController extends Controller
{
    public function __construct(
        private readonly CategoryTreeService $categoryTree,
        private readonly EffectiveCategoryAttributesResolver $effectiveAttributes
    ) {
    }

    public function index(Request $request)
    {
        $categorySlugs = $this->categorySlugs($request);
        $attributeFilters = $this->attributeFilters($request);

        if ($attributeFilters !== [] && count($categorySlugs) !== 1) {
            throw ValidationException::withMessages([
                'category' => [
                    'Exactly one active category is required when filtering by attributes.',
                ],
            ]);
        }

        $categories = $this->categoriesFor($categorySlugs);

        $query = Product::query()
            ->with([
                'categories:id,name,slug',
                'images:id,product_id,variant_id,path,alt_text,is_primary,sort_order',
                'attributeValues:id,attribute_id,label,value,hex_color,sort_order',
                'attributeValues.attribute:id,name,slug,type',
                'customAttributeValues:id,product_id,attribute_id,value_type,value_number,value_boolean,value_text',
                'customAttributeValues.attribute:id,name,slug,type',
                'variants:id,product_id,sku,price,compare_at_price,stock,is_active,combination_key',
                'variants.attributeValues:id,attribute_id,label,value,hex_color',
                'variants.attributeValues.attribute:id,name,slug,type',
            ])
            ->where('is_active', true);

        if ($categories->isNotEmpty()) {
            $categoryIds = $this->categoryTree->descendantIdsFor($categories);

            $query->whereHas(
                'categories',
                fn ($categoryQuery) => $categoryQuery->whereIn('categories.id', $categoryIds)
            );
        }

        if ($attributeFilters !== []) {
            $this->applyAttributeFilters(
                $query,
                $categories->first(),
                $attributeFilters
            );
        }

        $products = $query
            ->orderByDesc('published_at')
            ->orderByDesc('created_at')
            ->paginate(12);

        return ProductResource::collection($products);
    }

    /**
     * @return list<string>
     */
    private function categorySlugs(Request $request): array
    {
        $categories = $request->input('category', $request->input('categories', []));
        $categories = is_array($categories)
            ? $categories
            : explode(',', (string) $categories);

        return collect($categories)
            ->filter(fn ($slug) => is_string($slug) && $slug !== '')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<string, list<string>>
     */
    private function attributeFilters(Request $request): array
    {
        return collect($request->except([
            'category',
            'categories',
            'page',
        ]))
            ->map(function ($values) {
                $values = is_array($values)
                    ? $values
                    : explode(',', (string) $values);

                return collect($values)
                    ->filter(fn ($value) => is_string($value) && $value !== '')
                    ->unique()
                    ->values()
                    ->all();
            })
            ->filter(fn (array $values) => $values !== [])
            ->all();
    }

    /**
     * @param list<string> $categorySlugs
     * @return \Illuminate\Support\Collection<int, Category>
     */
    private function categoriesFor(array $categorySlugs)
    {
        if ($categorySlugs === []) {
            return collect();
        }

        $categories = Category::query()
            ->whereIn('slug', $categorySlugs)
            ->where('is_active', true)
            ->get();

        if ($categories->count() !== count($categorySlugs)) {
            throw ValidationException::withMessages([
                'category' => ['One or more categories are invalid or inactive.'],
            ]);
        }

        return $categories;
    }

    /**
     * @param array<string, list<string>> $attributeFilters
     */
    private function applyAttributeFilters(
        $query,
        Category $category,
        array $attributeFilters
    ): void {
        $allowedAttributes = $this->effectiveAttributes
            ->for($category)
            ->filter(fn ($attribute) => $attribute->pivot->is_filterable)
            ->keyBy('slug');

        foreach ($attributeFilters as $attributeSlug => $values) {
            $attribute = $allowedAttributes->get($attributeSlug);

            if ($attribute === null) {
                throw ValidationException::withMessages([
                    $attributeSlug => ['This filter is not available for the selected category.'],
                ]);
            }

            if (in_array($attribute->type, ['number', 'boolean', 'text'], true)) {
                $this->applyCustomAttributeFilter(
                    $query,
                    $attribute,
                    $attributeSlug,
                    $values
                );

                continue;
            }

            $allowedValues = $attribute->values->pluck('value');

            if (collect($values)->diff($allowedValues)->isNotEmpty()) {
                throw ValidationException::withMessages([
                    $attributeSlug => ['One or more filter values are invalid.'],
                ]);
            }

            $query->where(function ($productQuery) use ($attribute, $values) {
                $productQuery
                    ->whereHas('attributeValues', function ($valueQuery) use ($attribute, $values) {
                        $valueQuery
                            ->where('attribute_values.attribute_id', $attribute->id)
                            ->whereIn('attribute_values.value', $values);
                    })
                    ->orWhereHas('variants', function ($variantQuery) use ($attribute, $values) {
                        $variantQuery
                            ->where('is_active', true)
                            ->whereHas('attributeValues', function ($valueQuery) use ($attribute, $values) {
                                $valueQuery
                                    ->where('attribute_values.attribute_id', $attribute->id)
                                    ->whereIn('attribute_values.value', $values);
                            });
                    });
            });
        }
    }

    /**
     * @param list<string> $values
     */
    private function applyCustomAttributeFilter(
        $query,
        $attribute,
        string $attributeSlug,
        array $values
    ): void {
        if ($attribute->type === 'number') {
            if (collect($values)->contains(fn ($value) => ! is_numeric($value))) {
                throw ValidationException::withMessages([
                    $attributeSlug => ['All filter values must be numeric.'],
                ]);
            }

            $query->whereHas('customAttributeValues', function ($valueQuery) use ($attribute, $values) {
                $valueQuery
                    ->where('attribute_id', $attribute->id)
                    ->whereIn('value_number', $values);
            });

            return;
        }

        if ($attribute->type === 'boolean') {
            $booleanValues = collect($values)->map(function ($value) use ($attributeSlug) {
                if (! in_array($value, ['0', '1', 'true', 'false'], true)) {
                    throw ValidationException::withMessages([
                        $attributeSlug => ['All filter values must be boolean.'],
                    ]);
                }

                return filter_var($value, FILTER_VALIDATE_BOOLEAN);
            });

            $query->whereHas('customAttributeValues', function ($valueQuery) use ($attribute, $booleanValues) {
                $valueQuery
                    ->where('attribute_id', $attribute->id)
                    ->whereIn('value_boolean', $booleanValues->all());
            });

            return;
        }

        $query->whereHas('customAttributeValues', function ($valueQuery) use ($attribute, $values) {
            $valueQuery
                ->where('attribute_id', $attribute->id)
                ->whereIn('value_text', $values);
        });
    }
}
