<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MobileIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_update_profile_validate_cart_and_checkout_idempotently(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('mobile')->plainTextToken;
        $category = Category::where('name', 'peripherals')->firstOrFail();
        $product = Product::create([
            'name' => 'Keyboard',
            'description' => 'Mechanical keyboard',
            'price' => 1500000,
            'stock' => 3,
            'category_id' => $category->id,
            'image_url' => 'https://example.com/keyboard.jpg',
        ]);

        $headers = ['Authorization' => "Bearer {$token}"];

        $this->getJson('/api/products?keywords=mechanical')
            ->assertOk()
            ->assertJsonPath('data.0.id', $product->id)
            ->assertJsonPath('data.0.price', 1500000);

        $this->withHeaders($headers)->putJson('/api/profile', [
            'name' => 'Customer One',
            'phone_number' => '08123456789',
            'default_address' => [
                'address' => 'Jalan Satu',
                'city' => 'Jakarta',
                'country_region' => 'Indonesia',
                'postcode' => '12345',
                'latitude' => null,
                'longitude' => null,
            ],
        ])->assertOk()
            ->assertJsonPath('default_address.city', 'Jakarta');

        $items = [['product_id' => $product->id, 'quantity' => 2]];
        $this->withHeaders($headers)->postJson('/api/cart/validate', compact('items'))
            ->assertOk()
            ->assertJsonPath('subtotal', 3000000)
            ->assertJsonPath('items.0.quantity', 2);

        $payload = [
            'items' => $items,
            'shipping_address' => [
                'address' => 'Jalan Satu',
                'city' => 'Jakarta',
                'country_region' => 'Indonesia',
                'postcode' => '12345',
                'phone_number' => '08123456789',
                'latitude' => null,
                'longitude' => null,
            ],
        ];
        $checkoutHeaders = [...$headers, 'Idempotency-Key' => 'checkout-1'];

        $first = $this->withHeaders($checkoutHeaders)->postJson('/api/orders', $payload)
            ->assertCreated()
            ->assertJsonPath('data.total_amount', 3000000)
            ->assertJsonCount(1, 'data.items');

        $this->withHeaders($checkoutHeaders)->postJson('/api/orders', $payload)
            ->assertOk()
            ->assertJsonPath('idempotent_replay', true)
            ->assertJsonPath('data.id', $first->json('data.id'));

        $this->assertDatabaseHas('products', ['id' => $product->id, 'stock' => 1]);

        $this->withHeaders($headers)->getJson('/api/orders?include=items.product')
            ->assertOk()
            ->assertJsonPath('data.0.items.0.product.name', 'Keyboard');

        $this->withHeaders($headers)->getJson('/api/orders/'.$first->json('data.id'))
            ->assertOk()
            ->assertJsonPath('data.shipping_address.phone_number', '08123456789');
    }

    public function test_checkout_rejects_insufficient_stock_without_creating_order(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('mobile')->plainTextToken;
        $category = Category::where('name', 'peripherals')->firstOrFail();
        $product = Product::create([
            'name' => 'Mouse',
            'description' => 'Wireless mouse',
            'price' => 500000,
            'stock' => 1,
            'category_id' => $category->id,
            'image_url' => 'https://example.com/mouse.jpg',
        ]);

        $this->withHeaders([
            'Authorization' => "Bearer {$token}",
            'Idempotency-Key' => 'checkout-no-stock',
        ])->postJson('/api/orders', [
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
            'shipping_address' => [
                'address' => 'Jalan Dua',
                'city' => 'Bandung',
                'country_region' => 'Indonesia',
                'postcode' => '40111',
                'phone_number' => '0812',
            ],
        ])->assertUnprocessable();

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseHas('products', ['id' => $product->id, 'stock' => 1]);
    }
}
