<?php

namespace Tests\Feature;

use App\Models\Attribute;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductCustomAttributeValue;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProductCustomAttributeValueTest extends TestCase
{
    use RefreshDatabase;

    public function test_scalar_attribute_values_are_exposed_and_filterable(): void
    {
        $category = Category::create([
            'name' => 'Engine',
            'slug' => 'engine',
            'is_active' => true,
        ]);

        $weight = $this->attribute('Weight', 'weight', 'number');
        $genuine = $this->attribute('Genuine', 'genuine', 'boolean');
        $note = $this->attribute('Note', 'note', 'text');

        foreach ([$weight, $genuine, $note] as $attribute) {
            $category->attributes()->attach($attribute->id, [
                'is_filterable' => true,
                'is_required' => false,
                'is_variant_axis' => false,
            ]);
        }

        $product = Product::create([
            'name' => 'Engine mount',
            'slug' => 'engine-mount',
            'price' => 100,
            'is_active' => true,
        ]);
        $product->categories()->attach($category);

        ProductCustomAttributeValue::create([
            'product_id' => $product->id,
            'attribute_id' => $weight->id,
            'value_type' => 'number',
            'value_number' => 12.5,
        ]);
        ProductCustomAttributeValue::create([
            'product_id' => $product->id,
            'attribute_id' => $genuine->id,
            'value_type' => 'boolean',
            'value_boolean' => true,
        ]);
        ProductCustomAttributeValue::create([
            'product_id' => $product->id,
            'attribute_id' => $note->id,
            'value_type' => 'text',
            'value_text' => 'Suitable for TU5',
        ]);

        $this->getJson('/api/products?category=engine&weight=12.5')
            ->assertOk()
            ->assertJsonPath('data.0.slug', 'engine-mount')
            ->assertJsonPath('data.0.custom_attributes.0.value', 12.5);

        $this->getJson('/api/products?category=engine&genuine=true')
            ->assertOk()
            ->assertJsonPath('data.0.slug', 'engine-mount');

        $this->getJson('/api/products?category=engine&note=Suitable%20for%20TU5')
            ->assertOk()
            ->assertJsonPath('data.0.slug', 'engine-mount');
    }

    public function test_admin_product_api_rejects_custom_values_for_select_attributes(): void
    {
        $this->authenticate();
        $select = $this->attribute('Brand', 'brand', 'select');

        $this->postJson('/api/admin/products', [
            'name' => 'Brake pad',
            'slug' => 'brake-pad',
            'price' => 100,
            'custom_attribute_values' => [[
                'attribute_id' => $select->id,
                'value' => 'Brembo',
            ]],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('custom_attribute_values.0.value');
    }

    public function test_admin_product_api_stores_a_number_custom_value(): void
    {
        $this->authenticate();
        $weight = $this->attribute('Weight', 'weight', 'number');

        $this->postJson('/api/admin/products', [
            'name' => 'Water pump',
            'slug' => 'water-pump',
            'price' => 100,
            'custom_attribute_values' => [[
                'attribute_id' => $weight->id,
                'value' => 1.75,
            ]],
        ])->assertCreated();

        $this->assertDatabaseHas('product_custom_attribute_values', [
            'attribute_id' => $weight->id,
            'value_type' => 'number',
            'value_number' => 1.75,
        ]);
    }

    private function attribute(string $name, string $slug, string $type): Attribute
    {
        return Attribute::create([
            'name' => $name,
            'slug' => $slug,
            'type' => $type,
        ]);
    }

    private function authenticate(): void
    {
        Sanctum::actingAs(User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]), ['admin']);
    }
}
