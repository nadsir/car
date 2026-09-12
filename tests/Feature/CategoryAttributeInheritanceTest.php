<?php

namespace Tests\Feature;

use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryAttributeInheritanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_child_configuration_overrides_an_inherited_attribute_configuration(): void
    {
        $root = $this->category('Car parts', 'car-parts');
        $brakes = $this->category('Brakes', 'brakes', $root->id);
        $pads = $this->category('Brake pads', 'brake-pads', $brakes->id);

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

        AttributeValue::create([
            'attribute_id' => $brand->id,
            'label' => 'Brembo',
            'value' => 'brembo',
        ]);

        $root->attributes()->attach($brand->id, [
            'is_filterable' => true,
            'is_required' => true,
            'is_variant_axis' => false,
            'sort_order' => 2,
        ]);
        $root->attributes()->attach($material->id, [
            'is_filterable' => true,
            'is_required' => false,
            'is_variant_axis' => false,
            'sort_order' => 1,
        ]);

        $pads->attributes()->attach($brand->id, [
            'is_filterable' => true,
            'is_required' => false,
            'is_variant_axis' => true,
            'sort_order' => 1,
        ]);
        $pads->attributes()->attach($material->id, [
            'is_filterable' => false,
            'is_required' => false,
            'is_variant_axis' => false,
            'sort_order' => 2,
        ]);

        $this->getJson('/api/categories/brake-pads/filters')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'brand')
            ->assertJsonPath('data.0.is_required', false)
            ->assertJsonPath('data.0.is_variant_axis', true)
            ->assertJsonPath('data.0.values.0.value', 'brembo');
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
}
