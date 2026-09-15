<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CustomerOrderTest extends TestCase
{
    use RefreshDatabase;

    private function customer(): User
    {
        return User::factory()->create(['role' => 'customer', 'is_active' => true]);
    }

    private function order(User $user, array $overrides = []): Order
    {
        return $user->orders()->create(array_merge([
            'status' => 'pending', 'subtotal' => '200.50', 'discount' => '10.00',
            'shipping_cost' => '20.00', 'total' => '210.50',
            'customer_name' => 'Recipient', 'customer_phone' => '09123456789',
            'customer_email' => 'recipient@example.com', 'shipping_address' => 'Street 12',
            'shipping_postal_code' => '1234567890', 'shipping_city' => 'Tehran',
            'shipping_province' => 'Tehran', 'notes' => 'Call first',
        ], $overrides));
    }

    private function item(Order $order, array $overrides = [])
    {
        return $order->items()->create(array_merge([
            'product_id' => null, 'product_variant_id' => null, 'product_name' => 'Original product',
            'sku' => 'ORIGINAL-SKU', 'quantity' => 2, 'unit_price' => '100.25', 'subtotal' => '200.50',
            'attributes' => [['attribute' => 'Color', 'slug' => 'color', 'label' => 'Black', 'value' => 'black']],
        ], $overrides));
    }

    private function product(): Product
    {
        return Product::create([
            'name' => 'Current product', 'slug' => fake()->uuid(),
            'price' => '999.00', 'stock' => 10, 'is_active' => true,
        ]);
    }

    public function test_guest_cannot_list_orders(): void
    {
        $this->getJson('/api/customer/orders')->assertUnauthorized();
    }

    public function test_guest_cannot_view_order(): void
    {
        $order = $this->order($this->customer());
        $this->getJson("/api/customer/orders/{$order->id}")->assertUnauthorized();
    }

    public function test_customer_lists_only_own_orders_with_counts(): void
    {
        $user = $this->customer();
        $own = $this->order($user);
        $other = $this->order($this->customer());
        $this->item($own);
        Sanctum::actingAs($user, ['customer']);
        $this->getJson('/api/customer/orders')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $own->id)->assertJsonPath('data.0.items_count', 1)
            ->assertJsonPath('data.0.total', '210.50')->assertJsonPath('data.0.status', 'pending')
            ->assertJsonMissing(['id' => $other->id])->assertJsonMissingPath('data.0.customer_phone');
    }

    public function test_orders_are_sorted_by_date_then_id_newest_first(): void
    {
        $user = $this->customer();
        $newest = $this->order($user);
        $older = $this->order($user);
        $older->forceFill(['created_at' => $newest->created_at->copy()->subDay()])->save();
        $tie = $this->order($user);
        $tie->forceFill(['created_at' => $newest->created_at])->save();
        Sanctum::actingAs($user, ['customer']);
        $this->getJson('/api/customer/orders')->assertOk()
            ->assertJsonPath('data.0.id', $tie->id)->assertJsonPath('data.1.id', $newest->id)
            ->assertJsonPath('data.2.id', $older->id);
    }

    public function test_customer_can_view_own_order_and_items(): void
    {
        $user = $this->customer();
        $order = $this->order($user);
        $item = $this->item($order);
        Sanctum::actingAs($user, ['customer']);
        $this->getJson("/api/customer/orders/{$order->id}")->assertOk()
            ->assertJsonPath('order.id', $order->id)->assertJsonPath('order.status', 'pending')
            ->assertJsonCount(1, 'order.items')->assertJsonPath('order.items.0.id', $item->id)
            ->assertJsonPath('order.items.0.product_name', 'Original product')
            ->assertJsonPath('order.items.0.quantity', 2)->assertJsonPath('order.items.0.unit_price', '100.25')
            ->assertJsonPath('order.items.0.subtotal', '200.50')
            ->assertJsonPath('order.items.0.attributes.0.label', 'Black')
            ->assertJsonStructure(['order' => ['created_at']]);
    }

    public function test_customer_cannot_view_another_users_order_even_with_query_override(): void
    {
        $owner = $this->customer();
        $order = $this->order($owner);
        $this->item($order);
        Sanctum::actingAs($this->customer(), ['customer']);
        $this->getJson("/api/customer/orders/{$order->id}?user_id={$owner->id}")
            ->assertNotFound()->assertJsonMissingPath('order')
            ->assertDontSee('recipient@example.com')->assertDontSee('Original product');
    }

    public function test_nonexistent_order_returns_not_found(): void
    {
        Sanctum::actingAs($this->customer(), ['customer']);
        $this->getJson('/api/customer/orders/999999')->assertNotFound();
    }

    public function test_shipping_information_is_returned(): void
    {
        $user = $this->customer();
        $order = $this->order($user);
        Sanctum::actingAs($user, ['customer']);
        $this->getJson("/api/customer/orders/{$order->id}")->assertOk()
            ->assertJsonPath('order.customer_name', 'Recipient')
            ->assertJsonPath('order.customer_phone', '09123456789')
            ->assertJsonPath('order.customer_email', 'recipient@example.com')
            ->assertJsonPath('order.shipping_address', 'Street 12')
            ->assertJsonPath('order.shipping_postal_code', '1234567890')
            ->assertJsonPath('order.shipping_city', 'Tehran')
            ->assertJsonPath('order.shipping_province', 'Tehran')
            ->assertJsonPath('order.notes', 'Call first');
    }

    public function test_financial_totals_are_returned(): void
    {
        $user = $this->customer();
        $order = $this->order($user);
        Sanctum::actingAs($user, ['customer']);
        $this->getJson("/api/customer/orders/{$order->id}")->assertOk()
            ->assertJsonPath('order.subtotal', '200.50')->assertJsonPath('order.discount', '10.00')
            ->assertJsonPath('order.shipping_cost', '20.00')->assertJsonPath('order.total', '210.50');
    }

    public function test_available_product_variant_and_primary_image_are_returned_without_replacing_snapshot(): void
    {
        $user = $this->customer();
        $order = $this->order($user);
        $product = $this->product();
        $variant = $product->variants()->create([
            'combination_key' => 'black', 'sku' => 'NEW-SKU', 'price' => 555, 'stock' => 5, 'is_active' => true,
        ]);
        $product->images()->create(['path' => 'secondary.jpg', 'sort_order' => 0, 'is_primary' => false]);
        $product->images()->create(['path' => 'primary.jpg', 'sort_order' => 1, 'is_primary' => true]);
        $this->item($order, ['product_id' => $product->id, 'product_variant_id' => $variant->id]);
        $product->update(['is_active' => false]);
        Sanctum::actingAs($user, ['customer']);
        $this->getJson("/api/customer/orders/{$order->id}")->assertOk()
            ->assertJsonPath('order.items.0.product_id', $product->id)
            ->assertJsonPath('order.items.0.product_variant_id', $variant->id)
            ->assertJsonPath('order.items.0.image', 'primary.jpg')
            ->assertJsonPath('order.items.0.product_name', 'Original product')
            ->assertJsonPath('order.items.0.sku', 'ORIGINAL-SKU')
            ->assertJsonPath('order.items.0.unit_price', '100.25');
    }

    public function test_snapshot_remains_after_product_and_variant_are_deleted(): void
    {
        $user = $this->customer();
        $order = $this->order($user);
        $product = $this->product();
        $variant = $product->variants()->create(['combination_key' => 'black', 'stock' => 5]);
        $this->item($order, ['product_id' => $product->id, 'product_variant_id' => $variant->id]);
        $product->delete();
        Sanctum::actingAs($user, ['customer']);
        $this->getJson("/api/customer/orders/{$order->id}")->assertOk()
            ->assertJsonPath('order.items.0.product_id', null)
            ->assertJsonPath('order.items.0.product_variant_id', null)
            ->assertJsonPath('order.items.0.image', null)
            ->assertJsonPath('order.items.0.product_name', 'Original product')
            ->assertJsonPath('order.items.0.sku', 'ORIGINAL-SKU')
            ->assertJsonPath('order.items.0.attributes.0.label', 'Black');
    }

    public function test_orders_list_is_paginated_and_empty_list_is_valid(): void
    {
        $user = $this->customer();
        Sanctum::actingAs($user, ['customer']);
        $this->getJson('/api/customer/orders')->assertOk()->assertJsonCount(0, 'data');
        for ($i = 0; $i < 21; $i++) $this->order($user);
        $this->getJson('/api/customer/orders')->assertOk()->assertJsonCount(20, 'data')->assertJsonPath('total', 21);
        $this->getJson('/api/customer/orders?page=2')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_images_are_eager_loaded_for_multiple_items(): void
    {
        $user = $this->customer();
        $order = $this->order($user);
        for ($i = 0; $i < 4; $i++) {
            $product = $this->product();
            $product->images()->create(['path' => "product-{$i}.jpg", 'is_primary' => false]);
            $this->item($order, ['product_id' => $product->id]);
        }
        Sanctum::actingAs($user, ['customer']);
        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->getJson("/api/customer/orders/{$order->id}")->assertOk()->assertJsonCount(4, 'order.items')
            ->assertJsonPath('order.items.0.image', 'product-0.jpg');
        $imageQueries = collect(DB::getQueryLog())->filter(fn ($query) => str_contains($query['query'], 'product_images'));
        DB::disableQueryLog();
        $this->assertCount(1, $imageQueries);
    }

    public function test_orders_pages_resolve_to_vue_entry_point(): void
    {
        $this->get('/orders')->assertOk()->assertViewIs('welcome');
        $this->get('/orders/123')->assertOk()->assertViewIs('welcome');
    }
}
