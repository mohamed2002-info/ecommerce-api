<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\SubCategory;
use App\Services\PromotionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PromotionEngineTest extends TestCase
{
    use RefreshDatabase;

    private PromotionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(PromotionService::class);
    }

    public function test_percentage_discount_is_applied(): void
    {
        $product = Product::factory()->create(['price' => 100]);
        $promo = Promotion::factory()->percentage(20)->create(['target_type' => 'products']);
        $promo->products()->attach($product->id);

        $this->assertSame(80.0, $this->service->effectiveUnitPrice($product->fresh()));
    }

    public function test_fixed_discount_is_applied_and_never_negative(): void
    {
        $product = Product::factory()->create(['price' => 30]);
        $promo = Promotion::factory()->fixed(50)->create(['target_type' => 'products']);
        $promo->products()->attach($product->id);

        // 30 - 50 clamps to 0, not -20.
        $this->assertSame(0.0, $this->service->effectiveUnitPrice($product->fresh()));
    }

    public function test_expired_promotion_is_not_applied(): void
    {
        $product = Product::factory()->create(['price' => 100]);
        $promo = Promotion::factory()->percentage(50)->expired()->create(['target_type' => 'products']);
        $promo->products()->attach($product->id);

        $this->assertSame(100.0, $this->service->effectiveUnitPrice($product->fresh()));
    }

    public function test_paused_and_future_promotions_are_not_applied(): void
    {
        $product = Product::factory()->create(['price' => 100]);

        $paused = Promotion::factory()->percentage(50)->paused()->create(['target_type' => 'products']);
        $paused->products()->attach($product->id);

        $future = Promotion::factory()->percentage(50)->future()->create(['target_type' => 'products']);
        $future->products()->attach($product->id);

        $this->assertSame(100.0, $this->service->effectiveUnitPrice($product->fresh()));
    }

    public function test_category_promotion_applies_to_products_in_that_category(): void
    {
        $category = Category::factory()->create();
        $subCategory = SubCategory::factory()->create(['category_id' => $category->id]);
        $product = Product::factory()->create(['price' => 200, 'sub_category_id' => $subCategory->id]);

        Promotion::factory()->percentage(25)->create([
            'target_type' => 'category',
            'category_id' => $category->id,
        ]);

        $this->assertSame(150.0, $this->service->effectiveUnitPrice($product->fresh()));
    }

    public function test_all_catalog_promotion_applies_to_every_product(): void
    {
        $product = Product::factory()->create(['price' => 50]);
        Promotion::factory()->percentage(10)->all()->create();

        $this->assertSame(45.0, $this->service->effectiveUnitPrice($product->fresh()));
    }

    public function test_conflict_resolution_prefers_higher_priority(): void
    {
        $product = Product::factory()->create(['price' => 100]);

        // Lower priority but bigger discount...
        $big = Promotion::factory()->percentage(40)->create(['target_type' => 'products', 'priority' => 1]);
        $big->products()->attach($product->id);

        // ...higher priority wins even though its discount is smaller.
        $priority = Promotion::factory()->percentage(10)->create(['target_type' => 'products', 'priority' => 5]);
        $priority->products()->attach($product->id);

        $this->assertSame(90.0, $this->service->effectiveUnitPrice($product->fresh()));
    }

    public function test_conflict_resolution_prefers_bigger_discount_at_equal_priority(): void
    {
        $product = Product::factory()->create(['price' => 100]);

        $small = Promotion::factory()->percentage(10)->create(['target_type' => 'products', 'priority' => 3]);
        $small->products()->attach($product->id);

        $big = Promotion::factory()->percentage(30)->create(['target_type' => 'products', 'priority' => 3]);
        $big->products()->attach($product->id);

        $this->assertSame(70.0, $this->service->effectiveUnitPrice($product->fresh()));
    }

    public function test_pricing_payload_shape(): void
    {
        $product = Product::factory()->create(['price' => 100]);
        $promo = Promotion::factory()->percentage(20)->create(['target_type' => 'products']);
        $promo->products()->attach($product->id);

        $payload = $this->service->pricingPayload($product->fresh(), $promo);

        $this->assertSame(100.0, $payload['original_price']);
        $this->assertSame(80.0, $payload['discounted_price']);
        $this->assertTrue($payload['on_sale']);
        $this->assertSame('percentage', $payload['promotion']['discount_type']);
    }

    public function test_product_index_includes_promotion_pricing(): void
    {
        $product = Product::factory()->create(['price' => 100]);
        $promo = Promotion::factory()->percentage(20)->create(['target_type' => 'products']);
        $promo->products()->attach($product->id);

        $this->getJson('/api/products')
            ->assertStatus(200)
            ->assertJsonFragment([
                'id' => $product->id,
                'original_price' => 100.0,
                'discounted_price' => 80.0,
                'on_sale' => true,
            ]);
    }
}
