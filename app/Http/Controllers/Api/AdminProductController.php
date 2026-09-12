<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductCustomAttributeValue;
use App\Services\EffectiveCategoryAttributesResolver;
use App\Services\CategoryTreeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AdminProductController extends Controller
{
    public function __construct(
        private readonly EffectiveCategoryAttributesResolver $effectiveAttributes,
        private readonly CategoryTreeService $categoryTree
    ) {
    }

    public function meta()
    {
        $allCategories = Category::query()
            ->where('is_active', true)
            ->with('attributes.values')
            ->orderBy('sort_order')
            ->get();

        $categories = $this->categoryTree->build($allCategories);

        $attributes = \App\Models\Attribute::query()
            ->with([
                'values' => function ($query) {
                    $query->orderBy('sort_order');
                },
            ])
            ->orderBy('sort_order')
            ->get();

        $categoryAttributes = $this->effectiveAttributes
            ->forMany($allCategories)
            ->map(fn ($attributes) => $attributes->values())
            ->all();

        return response()->json([
            'categories' => $categories,
            'attributes' => $attributes,
            'category_attributes' => $categoryAttributes,
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateProduct($request);
        $this->validateCustomAttributeValues(
            $data['custom_attribute_values'] ?? []
        );
        $this->validateVariantAttributeValues(
            $data['variants'] ?? [],
            $data['category_ids'] ?? []
        );

        return DB::transaction(function () use ($data) {
            $categoryIds = $data['category_ids'] ?? [];
            $attributeValueIds = $data['attribute_value_ids'] ?? [];
            $customAttributeValues = $data['custom_attribute_values'] ?? [];
            $variants = $data['variants'] ?? [];

            unset(
                $data['category_ids'],
                $data['attribute_value_ids'],
                $data['custom_attribute_values'],
                $data['variants']
            );

            $product = Product::create($data);

            $this->syncProductCategories(
                $product,
                $categoryIds
            );

            $this->syncProductAttributes(
                $product,
                $attributeValueIds
            );

            $this->syncCustomAttributeValues(
                $product,
                $customAttributeValues
            );

            $this->syncVariants(
                $product,
                $variants
            );

            return response()->json(
                $product->load([
                    'categories',
                    'attributeValues.attribute',
                    'customAttributeValues.attribute',
                    'variants.attributeValues.attribute',
                ]),
                201
            );
        });
    }

    public function show(Product $product)
    {
        $product->load([
            'categories',
            'attributeValues.attribute',
            'customAttributeValues.attribute',
            'variants.attributeValues.attribute',
            'images',
        ]);

        return response()->json($product);
    }

    public function update(
        Request $request,
        Product $product
    ) {
        $data = $this->validateProduct(
            $request,
            $product
        );
        $this->validateCustomAttributeValues(
            $data['custom_attribute_values'] ?? []
        );
        $this->validateVariantAttributeValues(
            $data['variants'] ?? [],
            $data['category_ids'] ?? []
        );

        return DB::transaction(function () use (
            $data,
            $product
        ) {
            $categoryIds = $data['category_ids'] ?? [];
            $attributeValueIds = $data['attribute_value_ids'] ?? [];
            $customAttributeValues = $data['custom_attribute_values'] ?? [];
            $variants = $data['variants'] ?? [];

            unset(
                $data['category_ids'],
                $data['attribute_value_ids'],
                $data['custom_attribute_values'],
                $data['variants']
            );

            $product->update($data);

            $this->syncProductCategories(
                $product,
                $categoryIds
            );

            $this->syncProductAttributes(
                $product,
                $attributeValueIds
            );

            $this->syncCustomAttributeValues(
                $product,
                $customAttributeValues
            );

            /*
             * فعلاً Variantها از نو ساخته می‌شوند.
             * در مرحله بعدی که مدیریت Variant Image را اضافه کنیم،
             * این بخش را تغییر می‌دهیم تا ID و تصاویر Variantها حفظ شوند.
             */
            $product->variants()->delete();

            $this->syncVariants(
                $product,
                $variants
            );

            return response()->json(
                $product->fresh()->load([
                    'categories',
                    'attributeValues.attribute',
                    'customAttributeValues.attribute',
                    'variants.attributeValues.attribute',
                    'images',
                ])
            );
        });
    }

    public function destroy(Product $product)
    {
        $product->delete();

        return response()->noContent();
    }

    protected function validateProduct(
        Request $request,
        ?Product $product = null
    ): array {
        $productId = $product?->id;

        return $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
            ],

            'slug' => [
                'required',
                'string',
                'max:255',
                Rule::unique(
                    'products',
                    'slug'
                )->ignore($productId),
            ],

            'sku' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique(
                    'products',
                    'sku'
                )->ignore($productId),
            ],

            'short_description' => [
                'nullable',
                'string',
            ],

            'description' => [
                'nullable',
                'string',
            ],

            'price' => [
                'required',
                'numeric',
                'min:0',
            ],

            'compare_at_price' => [
                'nullable',
                'numeric',
                'min:0',
            ],

            'stock' => [
                'nullable',
                'integer',
                'min:0',
            ],

            'is_active' => [
                'boolean',
            ],

            'is_featured' => [
                'boolean',
            ],

            'category_ids' => [
                'nullable',
                'array',
            ],

            'category_ids.*' => [
                'integer',
                'exists:categories,id',
            ],

            'attribute_value_ids' => [
                'nullable',
                'array',
            ],

            'attribute_value_ids.*' => [
                'integer',
                'exists:attribute_values,id',
            ],

            'custom_attribute_values' => [
                'nullable',
                'array',
            ],

            'custom_attribute_values.*.attribute_id' => [
                'required',
                'integer',
                'distinct',
                'exists:attributes,id',
            ],

            'custom_attribute_values.*.value' => [
                'required',
            ],

            'variants' => [
                'nullable',
                'array',
            ],

            'variants.*.sku' => [
                'nullable',
                'string',
                'max:255',
            ],

            'variants.*.price' => [
                'nullable',
                'numeric',
                'min:0',
            ],

            'variants.*.compare_at_price' => [
                'nullable',
                'numeric',
                'min:0',
            ],

            'variants.*.stock' => [
                'nullable',
                'integer',
                'min:0',
            ],

            'variants.*.is_active' => [
                'boolean',
            ],

            'variants.*.attribute_value_ids' => [
                'nullable',
                'array',
            ],

            'variants.*.attribute_value_ids.*' => [
                'integer',
                'exists:attribute_values,id',
            ],
        ]);
    }

    protected function syncProductCategories(
        Product $product,
        array $categoryIds
    ): void {
        $product->categories()->sync(
            collect($categoryIds)
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values()
                ->all()
        );
    }

    protected function syncProductAttributes(
        Product $product,
        array $attributeValueIds
    ): void {
        $attributeValues = AttributeValue::query()
            ->whereIn('id', $attributeValueIds)
            ->get([
                'id',
                'attribute_id',
            ]);

        $productAttributes = [];

        foreach ($attributeValues as $value) {
            $productAttributes[$value->id] = [
                'attribute_id' => $value->attribute_id,
            ];
        }

        $product->attributeValues()->sync(
            $productAttributes
        );
    }

    protected function syncCustomAttributeValues(
        Product $product,
        array $customAttributeValues
    ): void {
        $attributeIds = collect($customAttributeValues)
            ->pluck('attribute_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $product->customAttributeValues()
            ->whereNotIn('attribute_id', $attributeIds)
            ->delete();

        foreach ($customAttributeValues as $valueData) {
            $attribute = Attribute::findOrFail($valueData['attribute_id']);
            $value = $valueData['value'];

            $data = [
                'value_type' => $attribute->type,
                'value_number' => null,
                'value_boolean' => null,
                'value_text' => null,
            ];

            if ($attribute->type === 'number') {
                $data['value_number'] = $value;
            } elseif ($attribute->type === 'boolean') {
                $data['value_boolean'] = filter_var(
                    $value,
                    FILTER_VALIDATE_BOOLEAN
                );
            } else {
                $data['value_text'] = $value;
            }

            ProductCustomAttributeValue::updateOrCreate(
                [
                    'product_id' => $product->id,
                    'attribute_id' => $attribute->id,
                ],
                $data
            );
        }
    }

    protected function validateCustomAttributeValues(array $customAttributeValues): void
    {
        if ($customAttributeValues === []) {
            return;
        }

        $attributes = Attribute::query()
            ->whereIn(
                'id',
                collect($customAttributeValues)->pluck('attribute_id')
            )
            ->get()
            ->keyBy('id');

        foreach ($customAttributeValues as $index => $valueData) {
            $attribute = $attributes->get($valueData['attribute_id']);
            $value = $valueData['value'];
            $key = "custom_attribute_values.{$index}.value";

            if ($attribute === null || ! in_array(
                $attribute->type,
                ['number', 'boolean', 'text'],
                true
            )) {
                throw ValidationException::withMessages([
                    $key => ['Only number, boolean, and text attributes accept custom values.'],
                ]);
            }

            if ($attribute->type === 'number' && ! is_numeric($value)) {
                throw ValidationException::withMessages([
                    $key => ['The value must be numeric.'],
                ]);
            }

            if ($attribute->type === 'boolean' && ! in_array(
                $value,
                [true, false, 0, 1, '0', '1', 'true', 'false'],
                true
            )) {
                throw ValidationException::withMessages([
                    $key => ['The value must be boolean.'],
                ]);
            }

            if ($attribute->type === 'text' && (! is_string($value) || mb_strlen($value) > 65535)) {
                throw ValidationException::withMessages([
                    $key => ['The value must be text with at most 65535 characters.'],
                ]);
            }
        }
    }

    protected function validateVariantAttributeValues(
        array $variants,
        array $categoryIds
    ): void {
        $variantValueIds = collect($variants)
            ->flatMap(
                fn (array $variant) => $variant['attribute_value_ids'] ?? []
            )
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($variantValueIds->isEmpty()) {
            return;
        }

        $categories = Category::query()
            ->whereIn('id', $categoryIds)
            ->get();

        $allowedAttributeIds = null;

        foreach ($categories as $category) {
            $categoryAxisIds = $this->effectiveAttributes
                ->for($category)
                ->filter(function (Attribute $attribute) {
                    return $attribute->pivot->is_variant_axis
                        && in_array(
                            $attribute->type,
                            ['select', 'multiselect', 'color'],
                            true
                        );
                })
                ->pluck('id');

            $allowedAttributeIds = $allowedAttributeIds === null
                ? $categoryAxisIds
                : $allowedAttributeIds->intersect($categoryAxisIds)->values();
        }

        $attributeValues = AttributeValue::query()
            ->whereIn('id', $variantValueIds)
            ->get(['id', 'attribute_id'])
            ->keyBy('id');

        foreach ($variants as $index => $variant) {
            foreach ($variant['attribute_value_ids'] ?? [] as $valueId) {
                $attributeValue = $attributeValues->get((int) $valueId);

                if (
                    $attributeValue === null
                    || $allowedAttributeIds === null
                    || ! $allowedAttributeIds->contains($attributeValue->attribute_id)
                ) {
                    throw ValidationException::withMessages([
                        "variants.{$index}.attribute_value_ids" => [
                            'Variant values must belong to a shared category variant axis.',
                        ],
                    ]);
                }
            }
        }
    }

    protected function syncVariants(
        Product $product,
        array $variants
    ): void {
        foreach ($variants as $variantData) {
            $variantValueIds =
                $variantData['attribute_value_ids'] ?? [];

            unset(
                $variantData['attribute_value_ids']
            );

            $uniqueValueIds = collect($variantValueIds)
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->sort()
                ->values()
                ->all();

            $variantData['combination_key'] =
                implode('-', $uniqueValueIds);

            if (
                $variantData['combination_key'] === ''
            ) {
                $variantData['combination_key'] =
                    !empty($variantData['sku'])
                        ? 'sku-' . $variantData['sku']
                        : uniqid(
                            'variant_',
                            true
                        );
            }

            $variant =
                $product->variants()->create(
                    $variantData
                );

            if (!empty($uniqueValueIds)) {
                $variant->attributeValues()->sync(
                    $uniqueValueIds
                );
            }
        }
    }
}
