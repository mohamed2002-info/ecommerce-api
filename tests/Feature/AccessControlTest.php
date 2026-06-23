<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccessControlTest extends TestCase
{
    use RefreshDatabase;

    private function tokenFor(User $user): string
    {
        $abilities = $user->role === 'admin' ? ['admin'] : [];
        return $user->createToken('test', $abilities)->plainTextToken;
    }

    public function test_cart_requires_authentication(): void
    {
        $this->getJson('/api/cart')->assertStatus(401);
    }

    public function test_cart_is_scoped_to_the_authenticated_user(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $product = Product::factory()->create();

        // Bob has an item in his cart.
        Cart::create(['user_id' => $bob->id, 'product_id' => $product->id, 'quantity' => 3]);

        // Alice authenticates and reads her own (empty) cart — she must NOT see Bob's.
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->tokenFor($alice))
            ->getJson('/api/cart');

        $response->assertStatus(200);
        $this->assertCount(0, $response->json('items'));
    }

    public function test_user_cannot_modify_another_users_cart(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $product = Product::factory()->create();
        Cart::create(['user_id' => $bob->id, 'product_id' => $product->id, 'quantity' => 3]);

        // Alice tries to delete a product from "the" cart. It only touches HER cart,
        // so Bob's row is untouched. (Old API trusted a client user_id — this proves it no longer does.)
        $this->withHeader('Authorization', 'Bearer ' . $this->tokenFor($alice))
            ->deleteJson("/api/cart/{$product->id}")
            ->assertStatus(200);

        $this->assertDatabaseHas('carts', [
            'user_id' => $bob->id,
            'product_id' => $product->id,
        ]);
    }

    public function test_adding_to_cart_uses_the_token_identity(): void
    {
        $alice = User::factory()->create();
        $product = Product::factory()->create();

        $this->withHeader('Authorization', 'Bearer ' . $this->tokenFor($alice))
            ->postJson('/api/cart', ['product_id' => $product->id, 'quantity' => 2])
            ->assertStatus(201);

        $this->assertDatabaseHas('carts', [
            'user_id' => $alice->id,
            'product_id' => $product->id,
            'quantity' => 2,
        ]);
    }

    public function test_non_admin_cannot_create_products(): void
    {
        $user = User::factory()->create(); // role = user
        $subCategory = \App\Models\SubCategory::factory()->create();

        $this->withHeader('Authorization', 'Bearer ' . $this->tokenFor($user))
            ->postJson('/api/products', [
                'name' => 'Hacked Product',
                'reference' => 'HACK-1',
                'sub_category_id' => $subCategory->id,
                'price' => 9.99,
                'description' => 'nope',
            ])
            ->assertStatus(403);

        $this->assertDatabaseMissing('products', ['reference' => 'HACK-1']);
    }

    public function test_unauthenticated_cannot_create_products(): void
    {
        $this->postJson('/api/products', ['name' => 'x'])->assertStatus(401);
    }

    public function test_admin_can_create_products(): void
    {
        $admin = User::factory()->admin()->create();
        $subCategory = \App\Models\SubCategory::factory()->create();

        $this->withHeader('Authorization', 'Bearer ' . $this->tokenFor($admin))
            ->postJson('/api/products', [
                'name' => 'Legit Product',
                'reference' => 'OK-1',
                'sub_category_id' => $subCategory->id,
                'price' => 19.99,
                'description' => 'a real product',
            ])
            ->assertStatus(201);

        $this->assertDatabaseHas('products', ['reference' => 'OK-1']);
    }

    public function test_products_are_publicly_readable(): void
    {
        Product::factory()->count(2)->create();
        $this->getJson('/api/products')->assertStatus(200);
    }
}
