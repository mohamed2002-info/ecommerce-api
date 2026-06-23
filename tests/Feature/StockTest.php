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

class StockTest extends TestCase
{
    use RefreshDatabase;

    private function token(User $user): string
    {
        $abilities = $user->role === 'admin' ? ['admin'] : [];
        return $user->createToken('test', $abilities)->plainTextToken;
    }

    private function stockFor(Product $p, int $sfax, int $tunis): array
    {
        $sfaxStore = Store::factory()->create(['city' => 'Sfax']);
        $tunisStore = Store::factory()->create(['city' => 'Tunis']);
        ProductStock::create(['product_id' => $p->id, 'store_id' => $sfaxStore->id, 'quantity' => $sfax]);
        ProductStock::create(['product_id' => $p->id, 'store_id' => $tunisStore->id, 'quantity' => $tunis]);
        return [$sfaxStore, $tunisStore];
    }

    public function test_product_index_exposes_stock_and_availability(): void
    {
        $product = Product::factory()->create();
        $this->stockFor($product, 3, 0); // Sfax 3, Tunis 0

        $response = $this->getJson('/api/products')->assertStatus(200);

        $item = collect($response->json())->firstWhere('id', $product->id);
        $this->assertSame(3, $item['total_stock']);
        $this->assertTrue($item['in_stock']);
        $this->assertEquals(['Sfax'], $item['available_stores']); // Tunis has 0, excluded
    }

    public function test_stores_endpoint_is_public(): void
    {
        Store::factory()->count(2)->create();
        $this->getJson('/api/stores')->assertStatus(200)->assertJsonStructure([['id', 'name', 'city', 'slug']]);
    }

    public function test_admin_can_set_per_store_stock(): void
    {
        $admin = User::factory()->admin()->create();
        $subCategory = \App\Models\SubCategory::factory()->create();
        $sfax = Store::factory()->create(['city' => 'Sfax']);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token($admin))
            ->postJson('/api/products', [
                'name' => 'Stocked Item',
                'reference' => 'STK-1',
                'sub_category_id' => $subCategory->id,
                'price' => 100,
                'description' => 'x',
                'stock' => [$sfax->id => 7],
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('product_stock', ['store_id' => $sfax->id, 'quantity' => 7]);
    }

    public function test_checkout_rejects_when_insufficient_stock(): void
    {
        Mail::fake();
        $user = User::factory()->create();
        $product = Product::factory()->create(['price' => 50]);
        $this->stockFor($product, 1, 0); // only 1 available

        Cart::create(['user_id' => $user->id, 'product_id' => $product->id, 'quantity' => 3]);

        $this->withHeader('Authorization', 'Bearer ' . $this->token($user))
            ->postJson('/api/orders/confirm')
            ->assertStatus(409)
            ->assertJson(['success' => false]);

        // No order created, stock untouched.
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseHas('product_stock', ['product_id' => $product->id, 'quantity' => 1]);
    }

    public function test_checkout_decrements_stock(): void
    {
        Mail::fake();
        $user = User::factory()->create();
        $product = Product::factory()->create(['price' => 50]);
        [$sfax, $tunis] = $this->stockFor($product, 2, 5); // total 7

        Cart::create(['user_id' => $user->id, 'product_id' => $product->id, 'quantity' => 4]);

        $this->withHeader('Authorization', 'Bearer ' . $this->token($user))
            ->postJson('/api/orders/confirm')
            ->assertStatus(200)
            ->assertJson(['success' => true]);

        // 4 taken: drains Sfax (2) then Tunis (2) -> Sfax 0, Tunis 3.
        $this->assertDatabaseHas('product_stock', ['store_id' => $sfax->id, 'quantity' => 0]);
        $this->assertDatabaseHas('product_stock', ['store_id' => $tunis->id, 'quantity' => 3]);
    }
}
