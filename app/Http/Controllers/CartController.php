<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        $items = Cart::where('user_id', $request->user_id)
            ->with('product')
            ->get();

        return response()->json([
            'items' => $items->map(fn ($item) => [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'quantity' => $item->quantity,
                'product' => $item->product,
            ]),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'user_id' => 'required|exists:users,id',
            'product_id' => 'required|exists:products,id',
            'quantity' => 'nullable|integer|min:1',
        ]);

        $quantity = $data['quantity'] ?? 1;

        $existing = Cart::where('user_id', $data['user_id'])
            ->where('product_id', $data['product_id'])
            ->first();

        if ($existing) {
            $existing->quantity += $quantity;
            $existing->save();
            return response()->json(['message' => 'Cart updated', 'quantity' => $existing->quantity]);
        }

        Cart::create([
            'user_id' => $data['user_id'],
            'product_id' => $data['product_id'],
            'quantity' => $quantity,
        ]);

        return response()->json(['message' => 'Added to cart', 'quantity' => $quantity], 201);
    }

    public function update(Request $request, int $productId)
    {
        $data = $request->validate([
            'user_id' => 'required|exists:users,id',
            'quantity' => 'required|integer|min:1',
        ]);

        $item = Cart::where('user_id', $data['user_id'])
            ->where('product_id', $productId)
            ->firstOrFail();

        $item->quantity = $data['quantity'];
        $item->save();

        return response()->json(['message' => 'Quantity updated', 'quantity' => $item->quantity]);
    }

    public function destroy(Request $request, int $productId)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        Cart::where('user_id', $request->user_id)
            ->where('product_id', $productId)
            ->delete();

        return response()->json(['message' => 'Removed from cart']);
    }

    public function clear(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        Cart::where('user_id', $request->user_id)->delete();

        return response()->json(['message' => 'Cart cleared']);
    }
}

