<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Promotion;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * The single source of truth for "what does this product cost right now".
 *
 * Resolution rules when several live promotions target the same product:
 *   1. Highest `priority` wins.
 *   2. Tie-break on the largest actual discount for that product.
 *   3. Final tie-break: the most recently created promotion.
 *
 * Only one promotion ever applies to a product at a time, which avoids
 * stacking/conflict bugs and keeps totals predictable.
 */
class PromotionService
{
    /**
     * Resolve the winning live promotion for each product id.
     *
     * @param  Collection<int,Product>  $products
     * @return array<int,Promotion|null>  keyed by product id
     */
    public function resolveForProducts(Collection $products, ?Carbon $now = null): array
    {
        $now = $now ?? Carbon::now();

        if ($products->isEmpty()) {
            return [];
        }

        $productIds = $products->pluck('id')->all();
        $categoryIds = $products->pluck('sub_category_id')->filter()->unique();

        // Map sub_category_id -> category_id so we can match category-targeted promos.
        $subToCategory = \App\Models\SubCategory::whereIn('id', $categoryIds)
            ->pluck('category_id', 'id');

        // Load every live promotion that could possibly apply, with attached products.
        $promotions = Promotion::live($now)
            ->with('products:id')
            ->get();

        $result = [];

        foreach ($products as $product) {
            $productCategoryId = $subToCategory[$product->sub_category_id] ?? null;

            $candidates = $promotions->filter(function (Promotion $promo) use ($product, $productCategoryId) {
                return $this->promotionTargetsProduct($promo, $product, $productCategoryId);
            });

            $result[$product->id] = $this->pickWinner($candidates, (float) $product->price);
        }

        return $result;
    }

    /**
     * Convenience: resolve a single product.
     */
    public function resolveForProduct(Product $product, ?Carbon $now = null): ?Promotion
    {
        return $this->resolveForProducts(collect([$product]), $now)[$product->id] ?? null;
    }

    /**
     * Decorate a product with pricing fields for API output.
     *
     * @return array{original_price:float,discounted_price:float,on_sale:bool,promotion:array|null}
     */
    public function pricingPayload(Product $product, ?Promotion $promotion): array
    {
        $original = round((float) $product->price, 2);
        $discounted = $promotion ? $promotion->discountedPrice($original) : $original;
        $onSale = $promotion !== null && $discounted < $original;

        return [
            'original_price' => $original,
            'discounted_price' => $onSale ? $discounted : $original,
            'on_sale' => $onSale,
            'promotion' => $onSale ? [
                'id' => $promotion->id,
                'name' => $promotion->name,
                'discount_type' => $promotion->discount_type,
                'value' => (float) $promotion->value,
                'ends_at' => optional($promotion->ends_at)->toIso8601String(),
            ] : null,
        ];
    }

    /**
     * The authoritative unit price for a product right now (after any promotion).
     * Used by cart/order totals so the server never trusts a client price.
     */
    public function effectiveUnitPrice(Product $product, ?Carbon $now = null): float
    {
        $promotion = $this->resolveForProduct($product, $now);
        $original = round((float) $product->price, 2);

        return $promotion ? $promotion->discountedPrice($original) : $original;
    }

    /**
     * Does a promotion apply to this product, by target type?
     */
    private function promotionTargetsProduct(Promotion $promo, Product $product, ?int $productCategoryId): bool
    {
        switch ($promo->target_type) {
            case 'all':
                return true;
            case 'category':
                return $promo->category_id !== null && $promo->category_id === $productCategoryId;
            case 'product':
            case 'products':
                return $promo->products->contains('id', $product->id);
            default:
                return false;
        }
    }

    /**
     * Choose the winning promotion among candidates for a given base price.
     *
     * @param  Collection<int,Promotion>  $candidates
     */
    private function pickWinner(Collection $candidates, float $basePrice): ?Promotion
    {
        if ($candidates->isEmpty()) {
            return null;
        }

        return $candidates
            ->sort(function (Promotion $a, Promotion $b) use ($basePrice) {
                // 1. Higher priority wins.
                if ($a->priority !== $b->priority) {
                    return $b->priority <=> $a->priority;
                }
                // 2. Larger discount wins (lower resulting price).
                $pa = $a->discountedPrice($basePrice);
                $pb = $b->discountedPrice($basePrice);
                if ($pa !== $pb) {
                    return $pa <=> $pb;
                }
                // 3. Newest wins.
                return $b->id <=> $a->id;
            })
            ->first();
    }
}
