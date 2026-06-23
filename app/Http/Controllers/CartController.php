<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Services\PromotionService;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function __construct(private PromotionService $promotions)
    {
    }

    public function index(Request $request)
    {
        $userId = $request->user()->id;

        $items = Cart::where('user_id', $userId)
            ->with('product')
            ->get();

        $products = $items->pluck('product')->filter()->values();
        $resolved = $this->promotions->resolveForProducts($products);

        $cartItems = $items->map(function ($item) use ($resolved) {
            $promotion = $item->product ? ($resolved[$item->product_id] ?? null) : null;
            $pricing = $item->product
                ? $this->promotions->pricingPayload($item->product, $promotion)
                : null;

            return [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'quantity' => $item->quantity,
                'product' => $item->product ? array_merge($item->product->toArray(), $pricing) : null,
                'unit_price' => $pricing['discounted_price'] ?? 0,
                'line_total' => round(($pricing['discounted_price'] ?? 0) * $item->quantity, 2),
            ];
        });

        return response()->json([
            'items' => $cartItems,
            'subtotal' => round($cartItems->sum('line_total'), 2),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity' => 'nullable|integer|min:1',
        ]);

        $userId = $request->user()->id;
        $quantity = $data['quantity'] ?? 1;

        $existing = Cart::where('user_id', $userId)
            ->where('product_id', $data['product_id'])
            ->first();

        if ($existing) {
            $existing->quantity += $quantity;
            $existing->save();
            return response()->json(['message' => 'Cart updated', 'quantity' => $existing->quantity]);
        }

        Cart::create([
            'user_id' => $userId,
            'product_id' => $data['product_id'],
            'quantity' => $quantity,
        ]);

        return response()->json(['message' => 'Added to cart', 'quantity' => $quantity], 201);
    }

    public function update(Request $request, int $productId)
    {
        $data = $request->validate([
            'quantity' => 'required|integer|min:1',
        ]);

        $item = Cart::where('user_id', $request->user()->id)
            ->where('product_id', $productId)
            ->firstOrFail();

        $item->quantity = $data['quantity'];
        $item->save();

        return response()->json(['message' => 'Quantity updated', 'quantity' => $item->quantity]);
    }

    public function destroy(Request $request, int $productId)
    {
        Cart::where('user_id', $request->user()->id)
            ->where('product_id', $productId)
            ->delete();

        return response()->json(['message' => 'Removed from cart']);
    }

    public function clear(Request $request)
    {
        Cart::where('user_id', $request->user()->id)->delete();

        return response()->json(['message' => 'Cart cleared']);
    }
}
