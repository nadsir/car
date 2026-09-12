<?php

namespace Tests\Feature;

use App\Models\Attribute;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminProductMetaTest extends TestCase
{
    use RefreshDatabase;

    public function test_meta_returns_all_category_depths_and_effective_attributes(): void
    {
        Sanctum::actingAs(User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]), ['admin']);

        $root = Category::create(['name' => 'Root', 'slug' => 'root']);
        $child = Category::create(['name' => 'Child', 'slug' => 'child', 'parent_id' => $root->id]);
        $leaf = Category::create(['name' => 'Leaf', 'slug' => 'leaf', 'parent_id' => $child->id]);
        $brand = Attribute::create(['name' => 'Brand', 'slug' => 'brand', 'type' => 'select']);
        $root->attributes()->attach($brand, [
            'is_required' => false,
            'is_filterable' => true,
            'is_variant_axis' => false,
            'sort_order' => 0,
        ]);

        $this->getJson('/api/admin/products/meta')
            ->assertOk()
            ->assertJsonPath('categories.0.children.0.children.0.slug', 'leaf')
            ->assertJsonPath("category_attributes.{$leaf->id}.0.slug", 'brand');
    }
}
