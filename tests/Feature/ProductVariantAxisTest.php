<?php

namespace Tests\Feature;

use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProductVariantAxisTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_product_endpoints_require_an_active_admin(): void
    {
        $this->postJson('/api/admin/products', [
            'name' => 'Brake pad',
            'slug' => 'brake-pad',
            'price' => 100,
        ])->assertUnauthorized();

        $this->actingAs(User::factory()->create(), 'sanctum');

        $this->postJson('/api/admin/products', [
            'name' => 'Brake pad',
            'slug' => 'brake-pad',
            'price' => 100,
        ])->assertForbidden();
    }

    public function test_variants_only_accept_values_from_category_variant_axes(): void
    {
        $this->authenticate();

        $category = Category::create([
            'name' => 'Brake pads',
            'slug' => 'brake-pads',
            'is_active' => true,
        ]);

        $color = Attribute::create([
            'name' => 'Color',
            'slug' => 'color',
            'type' => 'color',
        ]);
        $brand = Attribute::create([
            'name' => 'Brand',
            'slug' => 'brand',
            'type' => 'select',
        ]);

        $black = AttributeValue::create([
            'attribute_id' => $color->id,
            'label' => 'Black',
            'value' => 'black',
            'hex_color' => '#000000',
        ]);
        $brembo = AttributeValue::create([
            'attribute_id' => $brand->id,
            'label' => 'Brembo',
            'value' => 'brembo',
        ]);

        $category->attributes()->attach($color->id, [
            'is_filterable' => true,
            'is_required' => false,
            'is_variant_axis' => true,
        ]);
        $category->attributes()->attach($brand->id, [
            'is_filterable' => true,
            'is_required' => false,
            'is_variant_axis' => false,
        ]);

        $this->postJson('/api/admin/products', [
            'name' => 'Brake pad',
            'slug' => 'brake-pad',
            'price' => 100,
            'category_ids' => [$category->id],
            'variants' => [[
                'stock' => 2,
                'attribute_value_ids' => [$brembo->id],
            ]],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('variants.0.attribute_value_ids');

        $this->postJson('/api/admin/products', [
            'name' => 'Black brake pad',
            'slug' => 'black-brake-pad',
            'price' => 100,
            'category_ids' => [$category->id],
            'variants' => [[
                'stock' => 2,
                'attribute_value_ids' => [$black->id],
            ]],
        ])->assertCreated();
    }

    public function test_product_values_must_belong_to_an_effective_category_attribute(): void
    {
        $this->authenticate();

        $category = Category::create([
            'name' => 'Brake system',
            'slug' => 'brake-system',
            'is_active' => true,
        ]);
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
        $ceramic = AttributeValue::create([
            'attribute_id' => $material->id,
            'label' => 'Ceramic',
            'value' => 'ceramic',
        ]);

        $category->attributes()->attach($brand->id, [
            'is_filterable' => true,
            'is_required' => false,
            'is_variant_axis' => false,
        ]);

        $this->postJson('/api/admin/products', [
            'name' => 'Brake pad',
            'slug' => 'brake-pad',
            'price' => 100,
            'category_ids' => [$category->id],
            'attribute_value_ids' => [$ceramic->id],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('attribute_value_ids');

        $this->postJson('/api/admin/products', [
            'name' => 'Brembo brake pad',
            'slug' => 'brembo-brake-pad',
            'price' => 100,
            'category_ids' => [$category->id],
            'attribute_value_ids' => [$brembo->id],
        ])->assertCreated();
    }

    private function authenticate(): void
    {
        Sanctum::actingAs(User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]), ['admin']);
    }
}
