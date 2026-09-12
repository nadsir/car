<?php

namespace Tests\Feature;

use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_filters_are_selected_from_category_attribute_settings(): void
    {
        $category = Category::create([
            'name' => 'Brake pads',
            'slug' => 'brake-pads',
            'is_active' => true,
        ]);

        $included = Attribute::create([
            'name' => 'Brand',
            'slug' => 'brand',
            'type' => 'select',
            'is_filterable' => false,
            'is_required' => false,
        ]);

        $excluded = Attribute::create([
            'name' => 'Material',
            'slug' => 'material',
            'type' => 'select',
            'is_filterable' => true,
            'is_required' => true,
        ]);

        AttributeValue::create([
            'attribute_id' => $included->id,
            'label' => 'Brembo',
            'value' => 'brembo',
            'sort_order' => 1,
        ]);

        $category->attributes()->attach($included->id, [
            'is_filterable' => true,
            'is_required' => true,
            'is_variant_axis' => true,
            'sort_order' => 2,
        ]);

        $category->attributes()->attach($excluded->id, [
            'is_filterable' => false,
            'is_required' => false,
            'is_variant_axis' => false,
            'sort_order' => 1,
        ]);

        $this->getJson('/api/categories/brake-pads/filters')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'brand')
            ->assertJsonPath('data.0.is_filterable', true)
            ->assertJsonPath('data.0.is_required', true)
            ->assertJsonPath('data.0.is_variant_axis', true)
            ->assertJsonPath('data.0.sort_order', 2)
            ->assertJsonPath('data.0.values.0.label', 'Brembo');
    }
}
