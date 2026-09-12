<?php

namespace Tests\Feature;

use App\Models\Attribute;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CategoryAttributeManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_replace_a_categorys_direct_attribute_configuration(): void
    {
        Sanctum::actingAs($this->admin(), ['admin']);

        $category = Category::create([
            'name' => 'Brake pads',
            'slug' => 'brake-pads',
        ]);
        $brand = $this->attribute('Brand', 'brand', 'select');
        $color = $this->attribute('Color', 'color', 'color');

        $this->putJson("/api/admin/categories/{$category->id}/attributes", [
            'attributes' => [
                [
                    'attribute_id' => $brand->id,
                    'is_required' => true,
                    'is_filterable' => true,
                    'is_variant_axis' => false,
                    'sort_order' => 2,
                ],
                [
                    'attribute_id' => $color->id,
                    'is_required' => false,
                    'is_filterable' => true,
                    'is_variant_axis' => true,
                    'sort_order' => 1,
                ],
            ],
        ])
            ->assertOk()
            ->assertJsonPath('data.0.id', $color->id)
            ->assertJsonPath('data.0.pivot.is_variant_axis', true)
            ->assertJsonPath('data.1.id', $brand->id)
            ->assertJsonPath('data.1.pivot.is_required', true);

        $this->assertDatabaseHas('category_attributes', [
            'category_id' => $category->id,
            'attribute_id' => $color->id,
            'is_variant_axis' => true,
            'sort_order' => 1,
        ]);

        $this->getJson("/api/admin/categories/{$category->id}/attributes")
            ->assertOk()
            ->assertJsonPath('data.0.id', $color->id)
            ->assertJsonPath('data.0.pivot.is_variant_axis', true);
    }

    public function test_number_attribute_cannot_be_a_variant_axis(): void
    {
        Sanctum::actingAs($this->admin(), ['admin']);

        $category = Category::create([
            'name' => 'Brake pads',
            'slug' => 'brake-pads',
        ]);
        $weight = $this->attribute('Weight', 'weight', 'number');

        $this->putJson("/api/admin/categories/{$category->id}/attributes", [
            'attributes' => [[
                'attribute_id' => $weight->id,
                'is_required' => false,
                'is_filterable' => true,
                'is_variant_axis' => true,
                'sort_order' => 0,
            ]],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('attributes');
    }

    private function admin(): User
    {
        return User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
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
}
