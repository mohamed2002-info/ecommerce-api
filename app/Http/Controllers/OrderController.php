<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductStock;
use App\Services\PromotionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class OrderController extends Controller
{
    public function __construct(private PromotionService $promotions)
    {
    }

    public function confirmOrder(Request $request)
    {
        $user = $request->user();
        $userId = $user->id;

        // Get cart items
        $cartItems = Cart::where('user_id', $userId)
            ->with('product')
            ->get();

        // Validate that cart is not empty
        if ($cartItems->isEmpty()) {
            return response()->json([
                'message' => 'Your cart is empty. Please add items before confirming your order.',
                'success' => false,
            ], 400);
        }

        // Resolve the authoritative, promotion-aware unit price for each item.
        // The client never supplies prices; promotions are re-evaluated here so a
        // discount that expired between page load and checkout is not honored.
        $unitPrices = [];
        foreach ($cartItems as $item) {
            $unitPrices[$item->id] = $item->product
                ? $this->promotions->effectiveUnitPrice($item->product)
                : 0;
        }

        $total = $cartItems->sum(fn ($item) => $unitPrices[$item->id] * $item->quantity);

        DB::beginTransaction();

        try {
            // Lock the stock rows for the cart's products and verify availability
            // before charging. Rejects the whole order if anything is short.
            $productIds = $cartItems->pluck('product_id')->all();
            $stockRows = ProductStock::whereIn('product_id', $productIds)
                ->lockForUpdate()
                ->get()
                ->groupBy('product_id');

            foreach ($cartItems as $cartItem) {
                $available = ($stockRows[$cartItem->product_id] ?? collect())->sum('quantity');
                if ($available < $cartItem->quantity) {
                    DB::rollBack();
                    $name = $cartItem->product->name ?? ('product #' . $cartItem->product_id);
                    return response()->json([
                        'message' => "Not enough stock for \"{$name}\". Only {$available} left.",
                        'success' => false,
                    ], 409);
                }
            }

            $order = Order::create([
                'user_id' => $userId,
                'total'   => $total,
                'status'  => 'pending',
            ]);

            foreach ($cartItems as $cartItem) {
                OrderItem::create([
                    'order_id'   => $order->id,
                    'product_id' => $cartItem->product_id,
                    'quantity'   => $cartItem->quantity,
                    // Store the discounted price actually charged, not the list price.
                    'price'      => $unitPrices[$cartItem->id],
                ]);

                // Decrement stock greedily across the product's stores.
                $this->decrementStock($stockRows[$cartItem->product_id] ?? collect(), $cartItem->quantity);
            }

            // Clear the cart inside the transaction so an order is never left
            // with a still-populated cart.
            Cart::where('user_id', $userId)->delete();

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Failed to create order: ' . $e->getMessage());

            return response()->json([
                'message' => 'An error occurred while processing your order. Please try again.',
                'success' => false,
            ], 500);
        }

        // Send the confirmation email after commit; a mail failure must not roll
        // back a successfully-placed order.
        try {
            $orderDetails = [
                'user'      => $user,
                'items'     => $cartItems,
                'total'     => $total,
                'orderDate' => now()->format('Y-m-d H:i:s'),
            ];

            Mail::send('emails.order-confirmation', $orderDetails, function ($message) use ($user) {
                $message->to($user->email, $user->name)
                    ->subject('Order Confirmation - Your Order Has Been Received');
            });
        } catch (\Throwable $e) {
            Log::error('Failed to send order confirmation email: ' . $e->getMessage());
        }

        return response()->json([
            'message'  => 'Order confirmed successfully. A confirmation email has been sent to your inbox.',
            'success'  => true,
            'order_id' => $order->id,
        ], 200);
    }

    /**
     * Subtract `$quantity` from the given (locked) stock rows, draining one
     * store at a time until the quantity is satisfied.
     *
     * @param  \Illuminate\Support\Collection<int,ProductStock>  $rows
     */
    private function decrementStock($rows, int $quantity): void
    {
        foreach ($rows as $row) {
            if ($quantity <= 0) {
                break;
            }
            $take = min($row->quantity, $quantity);
            if ($take > 0) {
                $row->quantity -= $take;
                $row->save();
                $quantity -= $take;
            }
        }
    }
}
