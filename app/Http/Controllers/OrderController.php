<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Models\User;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    public function confirmOrder(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        $userId = $request->user_id;
        
        // Get cart items
        $cartItems = Cart::where('user_id', $userId)
            ->with('product')
            ->get();

        // Validate that cart is not empty
        if ($cartItems->isEmpty()) {
            return response()->json([
                'message' => 'Your cart is empty. Please add items before confirming your order.',
                'success' => false
            ], 400);
        }

        // Get user information
        $user = User::findOrFail($userId);

        // Calculate total
        $total = $cartItems->sum(function ($item) {
            return ($item->product->price ?? 0) * $item->quantity;
        });

        // Create order in database using transaction
        DB::beginTransaction();
        
        try {
            // Create the order
            $order = Order::create([
                'user_id' => $userId,
                'total' => $total,
                'status' => 'pending',
            ]);

            // Create order items for each cart item
            foreach ($cartItems as $cartItem) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $cartItem->product_id,
                    'quantity' => $cartItem->quantity,
                    'price' => $cartItem->product->price ?? 0,
                ]);
            }

            DB::commit();

            // Prepare order details for email
            $orderDetails = [
                'user' => $user,
                'items' => $cartItems,
                'total' => $total,
                'orderDate' => now()->format('Y-m-d H:i:s'),
            ];

            // Send email notification (don't fail if email fails)
            try {
                Mail::send('emails.order-confirmation', $orderDetails, function ($message) use ($user) {
                    $message->to($user->email, $user->name)
                        ->subject('Order Confirmation - Your Order Has Been Received');
                });
            } catch (\Exception $e) {
                // Log email error but don't fail the order
                \Log::error('Failed to send order confirmation email: ' . $e->getMessage());
            }

            // Clear the cart after successful order creation
            Cart::where('user_id', $userId)->delete();

            return response()->json([
                'message' => 'Order confirmed successfully. A confirmation email has been sent to your inbox.',
                'success' => true,
                'order_id' => $order->id
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Failed to create order: ' . $e->getMessage());
            
            return response()->json([
                'message' => 'An error occurred while processing your order. Please try again.',
                'success' => false
            ], 500);
        }
    }
}

