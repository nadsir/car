<?php

namespace Tests\Feature;

use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_filters_only_accept_effective_filterable_category_attributes(): void
    {
        $root = $this->category('Car parts', 'car-parts');
        $brakes = $this->category('Brakes', 'brakes', $root->id);

        $brand = Attribute::create([
            'name' => 'Brand',
            'slug' => 'brand',
            'type' => 'select',
        ]);
        $material = Attribute::create([
            'name' => 'Material',
            'slug' => 'material',
            'type' => 'select',
        ]);

        $brembo = AttributeValue::create([
            'attribute_id' => $brand->id,
            'label' => 'Brembo',
            'value' => 'brembo',
        ]);
        $bosch = AttributeValue::create([
            'attribute_id' => $brand->id,
            'label' => 'Bosch',
            'value' => 'bosch',
        ]);

        $root->attributes()->attach($brand->id, [
            'is_filterable' => true,
            'is_required' => false,
            'is_variant_axis' => false,
        ]);
        $root->attributes()->attach($material->id, [
            'is_filterable' => false,
            'is_required' => false,
            'is_variant_axis' => false,
        ]);

        $bremboProduct = $this->product('Brembo brake pad', 'brembo-brake-pad');
        $bremboProduct->categories()->attach($brakes);
        $bremboProduct->attributeValues()->attach($brembo, [
            'attribute_id' => $brand->id,
        ]);

        $boschProduct = $this->product('Bosch brake pad', 'bosch-brake-pad');
        $boschProduct->categories()->attach($brakes);
        $boschProduct->attributeValues()->attach($bosch, [
            'attribute_id' => $brand->id,
        ]);

        $this->getJson('/api/products?category=brakes&brand=brembo')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'brembo-brake-pad');

        $this->getJson('/api/products?brand=brembo')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('category');

        $this->getJson('/api/products?category=brakes&material=steel')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('material');

        $this->getJson('/api/products?category=brakes&brand=unknown')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('brand');
    }

    public function test_product_filters_match_active_variant_attribute_values(): void
    {
        $category = $this->category('Brakes', 'brakes');
        $brand = Attribute::create([
            'name' => 'Brand',
            'slug' => 'brand',
            'type' => 'select',
        ]);
        $brembo = AttributeValue::create([
            'attribute_id' => $brand->id,
            'label' => 'Brembo',
            'value' => 'brembo',
        ]);

        $category->attributes()->attach($brand->id, [
            'is_filterable' => true,
            'is_required' => false,
            'is_variant_axis' => true,
        ]);

        $product = $this->product('Brake pad set', 'brake-pad-set');
        $product->categories()->attach($category);

        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'combination_key' => 'brand-brembo',
            'stock' => 3,
            'is_active' => true,
        ]);
        $variant->attributeValues()->attach($brembo);

        $this->getJson('/api/products?category=brakes&brand=brembo')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'brake-pad-set');
    }

    private function category(
        string $name,
        string $slug,
        ?int $parentId = null
    ): Category {
        return Category::create([
            'name' => $name,
            'slug' => $slug,
            'parent_id' => $parentId,
            'is_active' => true,
        ]);
    }

    private function product(string $name, string $slug): Product
    {
        return Product::create([
            'name' => $name,
            'slug' => $slug,
            'price' => 100,
            'is_active' => true,
        ]);
    }
}
