<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PromotionApiTest extends TestCase
{
    use RefreshDatabase;

    private function token(User $user): string
    {
        $abilities = $user->role === 'admin' ? ['admin'] : [];
        return $user->createToken('test', $abilities)->plainTextToken;
    }

    /** Give a product plenty of stock so checkout isn't blocked. */
    private function giveStock(Product $product, int $qty = 100): void
    {
        $store = \App\Models\Store::first() ?? \App\Models\Store::factory()->create();
        \App\Models\ProductStock::create(['product_id' => $product->id, 'store_id' => $store->id, 'quantity' => $qty]);
    }

    public function test_active_promotions_are_public_and_only_show_live_ones(): void
    {
        Promotion::factory()->percentage(20)->create(['name' => 'Live Deal']);     // live
        Promotion::factory()->percentage(50)->paused()->create(['name' => 'Paused']);
        Promotion::factory()->percentage(30)->expired()->create(['name' => 'Expired']);

        // No auth header — must be reachable by guests.
        $response = $this->getJson('/api/promotions/active');

        $response->assertStatus(200)
            ->assertJsonFragment(['name' => 'Live Deal'])
            ->assertJsonMissing(['name' => 'Paused'])
            ->assertJsonMissing(['name' => 'Expired']);

        // Must not leak internal fields.
        $first = $response->json()[0];
        $this->assertArrayNotHasKey('priority', $first);
        $this->assertArrayNotHasKey('audience', $first);
    }

    public function test_non_admin_cannot_manage_promotions(): void
    {
        $user = User::factory()->create();

        $this->withHeader('Authorization', 'Bearer ' . $this->token($user))
            ->getJson('/api/promotions')
            ->assertStatus(403);

        $this->withHeader('Authorization', 'Bearer ' . $this->token($user))
            ->postJson('/api/promotions', [])
            ->assertStatus(403);
    }

    public function test_unauthenticated_cannot_manage_promotions(): void
    {
        $this->getJson('/api/promotions')->assertStatus(401);
    }

    public function test_admin_can_create_a_product_promotion(): void
    {
        $admin = User::factory()->admin()->create();
        $product = Product::factory()->create(['price' => 100]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token($admin))
            ->postJson('/api/promotions', [
                'name' => 'Summer Sale',
                'target_type' => 'products',
                'product_ids' => [$product->id],
                'discount_type' => 'percentage',
                'value' => 25,
                'priority' => 1,
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('promotions', ['name' => 'Summer Sale', 'value' => 25]);
        $this->assertDatabaseHas('promotion_product', ['product_id' => $product->id]);
    }

    public function test_percentage_over_100_is_rejected(): void
    {
        $admin = User::factory()->admin()->create();
        $product = Product::factory()->create();

        $this->withHeader('Authorization', 'Bearer ' . $this->token($admin))
            ->postJson('/api/promotions', [
                'name' => 'Bad',
                'target_type' => 'products',
                'product_ids' => [$product->id],
                'discount_type' => 'percentage',
                'value' => 150,
            ])
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['value']]);
    }

    public function test_category_promotion_requires_category(): void
    {
        $admin = User::factory()->admin()->create();

        $this->withHeader('Authorization', 'Bearer ' . $this->token($admin))
            ->postJson('/api/promotions', [
                'name' => 'No category',
                'target_type' => 'category',
                'discount_type' => 'fixed',
                'value' => 10,
            ])
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['category_id']]);
    }

    public function test_admin_can_pause_a_promotion(): void
    {
        $admin = User::factory()->admin()->create();
        $promo = Promotion::factory()->create(['status' => 'active']);

        $this->withHeader('Authorization', 'Bearer ' . $this->token($admin))
            ->patchJson("/api/promotions/{$promo->id}/status", ['status' => 'paused'])
            ->assertStatus(200);

        $this->assertDatabaseHas('promotions', ['id' => $promo->id, 'status' => 'paused']);
    }

    public function test_checkout_total_reflects_active_promotion(): void
    {
        Mail::fake();

        $user = User::factory()->create();
        $product = Product::factory()->create(['price' => 100]);
        $this->giveStock($product);

        $promo = Promotion::factory()->percentage(20)->create(['target_type' => 'products']);
        $promo->products()->attach($product->id);

        Cart::create(['user_id' => $user->id, 'product_id' => $product->id, 'quantity' => 2]);

        $this->withHeader('Authorization', 'Bearer ' . $this->token($user))
            ->postJson('/api/orders/confirm')
            ->assertStatus(200)
            ->assertJson(['success' => true]);

        // 2 x (100 - 20%) = 2 x 80 = 160
        $this->assertDatabaseHas('orders', ['user_id' => $user->id, 'total' => 160.00]);
        $this->assertDatabaseHas('order_items', ['product_id' => $product->id, 'price' => 80.00]);
    }

    public function test_expired_promotion_is_not_honored_at_checkout(): void
    {
        Mail::fake();

        $user = User::factory()->create();
        $product = Product::factory()->create(['price' => 100]);
        $this->giveStock($product);

        $promo = Promotion::factory()->percentage(50)->expired()->create(['target_type' => 'products']);
        $promo->products()->attach($product->id);

        Cart::create(['user_id' => $user->id, 'product_id' => $product->id, 'quantity' => 1]);

        $this->withHeader('Authorization', 'Bearer ' . $this->token($user))
            ->postJson('/api/orders/confirm')
            ->assertStatus(200);

        // Full price charged because the promotion has expired.
        $this->assertDatabaseHas('orders', ['user_id' => $user->id, 'total' => 100.00]);
    }
}
