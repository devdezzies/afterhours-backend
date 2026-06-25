<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
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

    public function test_customer_can_checkout_multiple_items_with_map_coordinates(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('mobile')->plainTextToken;
        $category = Category::where('name', 'peripherals')->firstOrFail();
        $deskCategory = Category::where('name', 'desk_accessories')->firstOrFail();

        $bench = Product::create([
            'name' => 'Bench',
            'description' => 'Oak bench',
            'price' => 190290,
            'stock' => 5,
            'category_id' => $category->id,
            'image_url' => 'https://example.com/bench.jpg',
        ]);
        $stand = Product::create([
            'name' => 'Notebook Stand',
            'description' => 'Wood stand',
            'price' => 250000,
            'stock' => 4,
            'category_id' => $deskCategory->id,
            'image_url' => 'https://example.com/stand.jpg',
        ]);

        $payload = [
            'items' => [
                ['product_id' => $bench->id, 'quantity' => 1],
                ['product_id' => $stand->id, 'quantity' => 1],
            ],
            'shipping_address' => [
                'address' => 'asd',
                'city' => 'bandung',
                'country_region' => 'id',
                'postcode' => '232424',
                'phone_number' => '324253463',
                'latitude' => -6.2231178,
                'longitude' => 106.972705,
            ],
        ];

        $first = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
            'Idempotency-Key' => 'checkout-with-map',
        ])->postJson('/api/orders', $payload)
            ->assertCreated()
            ->assertJsonPath('data.total_amount', 440290)
            ->assertJsonPath('data.shipping_address.latitude', -6.2231178)
            ->assertJsonPath('data.shipping_address.longitude', 106.972705)
            ->assertJsonCount(2, 'data.items');

        $this->withHeaders([
            'Authorization' => "Bearer {$token}",
            'Idempotency-Key' => 'checkout-with-map',
        ])->postJson('/api/orders', $payload)
            ->assertOk()
            ->assertJsonPath('idempotent_replay', true)
            ->assertJsonPath('data.id', $first->json('data.id'));

        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('order_items', 2);
        $this->assertDatabaseHas('products', ['id' => $bench->id, 'stock' => 4]);
        $this->assertDatabaseHas('products', ['id' => $stand->id, 'stock' => 3]);
    }

    public function test_checkout_rejects_missing_product_without_creating_order(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('mobile')->plainTextToken;
        $missingProductId = (string) Str::uuid();

        $this->withHeaders([
            'Authorization' => "Bearer {$token}",
            'Idempotency-Key' => 'checkout-missing-product',
        ])->postJson('/api/orders', [
            'items' => [['product_id' => $missingProductId, 'quantity' => 1]],
            'shipping_address' => [
                'address' => 'Jalan Tiga',
                'city' => 'Jakarta',
                'country_region' => 'Indonesia',
                'postcode' => '12345',
                'phone_number' => '0812',
                'latitude' => -6.2,
                'longitude' => 106.8,
            ],
        ])->assertUnprocessable()
            ->assertJsonPath("errors.items.{$missingProductId}.0", 'Product is no longer available.');

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('order_items', 0);
    }
}
