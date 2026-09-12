<?php

namespace Tests\Feature;

use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AutomotiveSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeders_create_automotive_data_without_fashion_records(): void
    {
        $this->seed();

        $this->assertDatabaseHas('categories', ['slug' => 'car-parts']);
        $this->assertDatabaseHas('categories', ['slug' => 'brake-pads']);
        $this->assertDatabaseMissing('categories', ['slug' => 'clothing']);
        $this->assertDatabaseMissing('attributes', ['slug' => 'shoe-size']);
        $this->assertDatabaseMissing('products', ['slug' => 'white-satin-shirt']);

        $this->assertSame(46, Category::count());
        $this->assertSame(16, Attribute::count());
        $this->assertSame(47, AttributeValue::count());
        $this->assertSame(25, Product::count());
        $this->assertSame(4, ProductVariant::count());
        $this->assertSame(0, ProductImage::count());
    }

    public function test_seeded_brake_filters_are_category_specific_and_dynamic(): void
    {
        $this->seed();

        $this->getJson('/api/categories/brake-pads/filters')
            ->assertOk()
            ->assertJsonFragment(['slug' => 'brand'])
            ->assertJsonFragment(['slug' => 'axle-position'])
            ->assertJsonMissing(['slug' => 'material']);

        $this->getJson('/api/products?category=brake-pads&brand=bosch')
            ->assertOk()
            ->assertJsonPath('data.0.slug', 'front-brake-pad-peugeot-405');
    }
}
