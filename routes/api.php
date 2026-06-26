<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\SubCategoryController;
use App\Http\Controllers\WishlistController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PromotionController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\StoreController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Public routes are open to anyone. Routes inside the `auth:sanctum` group
| require a valid bearer token and resolve the current user from that token
| (never from a client-supplied user_id). Write operations on the catalog
| additionally require the `admin` ability via the `admin` middleware.
|
*/

/*
|--------------------------------------------------------------------------
| Public routes
|--------------------------------------------------------------------------
*/
// Health check for the hosting platform (Render). MUST be instant and must NOT
// touch the database: the DB is remote (Railway) and a slow query makes Render's
// health check time out, which leaves the deploy stuck "in progress" forever.
Route::get('health', function () {
    return response()->json(['status' => 'ok']);
});

Route::post('register', [UserController::class, 'register']);
Route::post('login', [UserController::class, 'login']);
Route::post('forgot-password', [UserController::class, 'forgotPassword']);
Route::post('reset-password', [UserController::class, 'resetPassword']);

// AI shopping assistant — public, but rate-limited to prevent abuse as a free
// LLM proxy (15 requests/minute per client).
Route::middleware('throttle:15,1')->post('assistant', [ChatController::class, 'chat']);

// Public catalog reads
Route::get('products', [ProductController::class, 'index']);
Route::get('products/{id}', [ProductController::class, 'show']);
Route::get('categories', [CategoryController::class, 'index']);
Route::get('categories/{id}', [CategoryController::class, 'show']);
Route::get('sub-categories', [SubCategoryController::class, 'index']);
Route::get('sub-categories/{id}', [SubCategoryController::class, 'show']);

// Public: currently-live promotions (for the storefront banner).
Route::get('promotions/active', [PromotionController::class, 'active']);

// Public: list of boutiques (Sfax / Tunis / Sousse).
Route::get('stores', [StoreController::class, 'index']);

/*
|--------------------------------------------------------------------------
| Authenticated routes (any logged-in user)
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', fn (Request $request) => $request->user());
    Route::post('logout', [UserController::class, 'logout']);

    // Wishlist (scoped to the authenticated user)
    Route::get('wishlist', [WishlistController::class, 'index']);
    Route::post('wishlist', [WishlistController::class, 'store']);
    Route::post('wishlist/toggle', [WishlistController::class, 'toggle']);
    Route::delete('wishlist/{productId}', [WishlistController::class, 'destroy']);
    Route::delete('wishlist', [WishlistController::class, 'clear']);

    // Cart (scoped to the authenticated user)
    Route::get('cart', [CartController::class, 'index']);
    Route::post('cart', [CartController::class, 'store']);
    Route::put('cart/{productId}', [CartController::class, 'update']);
    Route::delete('cart/{productId}', [CartController::class, 'destroy']);
    Route::delete('cart', [CartController::class, 'clear']);

    // Orders (scoped to the authenticated user)
    Route::post('orders/confirm', [OrderController::class, 'confirmOrder']);
});

/*
|--------------------------------------------------------------------------
| Admin-only routes (catalog management)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum', 'admin'])->group(function () {
    // Products
    Route::post('products', [ProductController::class, 'store']);
    Route::match(['put', 'post'], 'products/{id}', [ProductController::class, 'update']); // PUT or POST (method spoofing for multipart)
    Route::delete('products/{id}', [ProductController::class, 'destroy']);

    // Categories
    Route::post('categories', [CategoryController::class, 'store']);
    Route::put('categories/{id}', [CategoryController::class, 'update']);
    Route::delete('categories/{id}', [CategoryController::class, 'destroy']);

    // Sub-categories
    Route::post('sub-categories', [SubCategoryController::class, 'store']);
    Route::put('sub-categories/{id}', [SubCategoryController::class, 'update']);
    Route::delete('sub-categories/{id}', [SubCategoryController::class, 'destroy']);

    // Promotions (admin management)
    Route::get('promotions', [PromotionController::class, 'index']);
    Route::post('promotions', [PromotionController::class, 'store']);
    Route::get('promotions/{id}', [PromotionController::class, 'show']);
    Route::put('promotions/{id}', [PromotionController::class, 'update']);
    Route::patch('promotions/{id}/status', [PromotionController::class, 'setStatus']);
    Route::delete('promotions/{id}', [PromotionController::class, 'destroy']);
});
