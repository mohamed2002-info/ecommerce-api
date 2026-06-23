<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class OrderTest extends TestCase
{
    use RefreshDatabase;

    private function tokenFor(User $user): string
    {
        return $user->createToken('test')->plainTextToken;
    }

    /** Give a product plenty of stock so checkout isn't blocked. */
    private function giveStock(Product $product, int $qty = 100): void
    {
        $store = Store::first() ?? Store::factory()->create();
        ProductStock::create(['product_id' => $product->id, 'store_id' => $store->id, 'quantity' => $qty]);
    }

    public function test_confirm_order_requires_authentication(): void
    {
        $this->postJson('/api/orders/confirm')->assertStatus(401);
    }

    public function test_confirm_order_with_empty_cart_returns_400(): void
    {
        $user = User::factory()->create();

        $this->withHeader('Authorization', 'Bearer ' . $this->tokenFor($user))
            ->postJson('/api/orders/confirm')
            ->assertStatus(400)
            ->assertJson(['success' => false]);
    }

    public function test_confirm_order_creates_order_clears_cart_and_totals_server_side(): void
    {
        Mail::fake();

        $user = User::factory()->create();
        $p1 = Product::factory()->create(['price' => 10.00]);
        $p2 = Product::factory()->create(['price' => 2.50]);
        $this->giveStock($p1);
        $this->giveStock($p2);

        Cart::create(['user_id' => $user->id, 'product_id' => $p1->id, 'quantity' => 2]); // 20.00
        Cart::create(['user_id' => $user->id, 'product_id' => $p2->id, 'quantity' => 4]); // 10.00

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->tokenFor($user))
            ->postJson('/api/orders/confirm');

        $response->assertStatus(200)->assertJson(['success' => true]);

        // Total is computed from authoritative product prices: 20 + 10 = 30.00
        $this->assertDatabaseHas('orders', [
            'user_id' => $user->id,
            'total' => 30.00,
            'status' => 'pending',
        ]);
        $this->assertDatabaseCount('order_items', 2);

        // Cart is cleared after a successful order.
        $this->assertDatabaseMissing('carts', ['user_id' => $user->id]);
    }
}
