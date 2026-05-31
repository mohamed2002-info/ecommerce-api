<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Wishlist;
use Illuminate\Http\Request;

class WishlistController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        $wishlist = Wishlist::where('user_id', $request->user_id)
            ->with('product')
            ->get();

        return response()->json([
            'items' => $wishlist->map(fn ($item) => [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'product' => $item->product,
            ]),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'user_id' => 'required|exists:users,id',
            'product_id' => 'required|exists:products,id',
        ]);

        $existing = Wishlist::where('user_id', $data['user_id'])
            ->where('product_id', $data['product_id'])
            ->first();

        if ($existing) {
            return response()->json(['message' => 'Already in wishlist'], 200);
        }

        Wishlist::create($data);

        return response()->json(['message' => 'Added to wishlist'], 201);
    }

    public function destroy(Request $request, int $productId)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        Wishlist::where('user_id', $request->user_id)
            ->where('product_id', $productId)
            ->delete();

        return response()->json(['message' => 'Removed from wishlist']);
    }

    public function clear(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        Wishlist::where('user_id', $request->user_id)->delete();

        return response()->json(['message' => 'Wishlist cleared']);
    }

    public function toggle(Request $request)
    {
        $data = $request->validate([
            'user_id' => 'required|exists:users,id',
            'product_id' => 'required|exists:products,id',
        ]);

        $existing = Wishlist::where('user_id', $data['user_id'])
            ->where('product_id', $data['product_id'])
            ->first();

        if ($existing) {
            $existing->delete();
            return response()->json(['message' => 'Removed from wishlist']);
        }

        Wishlist::create($data);
        return response()->json(['message' => 'Added to wishlist'], 201);
    }
}

