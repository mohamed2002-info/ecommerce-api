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

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::post('register', [UserController::class, 'register']);
Route::post('login', [UserController::class, 'login']);
Route::post('forgot-password', [UserController::class, 'forgotPassword']);
Route::post('reset-password', [UserController::class, 'resetPassword']);

// Product routes
Route::get('products', [ProductController::class, 'index']);
Route::post('products', [ProductController::class, 'store']);
Route::get('products/{id}', [ProductController::class, 'show']);
Route::match(['put', 'post'], 'products/{id}', [ProductController::class, 'update']); // Allow both PUT and POST for method spoofing
Route::delete('products/{id}', [ProductController::class, 'destroy']);

// Category routes
Route::get('categories', [CategoryController::class, 'index']);
Route::post('categories', [CategoryController::class, 'store']);
Route::get('categories/{id}', [CategoryController::class, 'show']);
Route::put('categories/{id}', [CategoryController::class, 'update']);
Route::delete('categories/{id}', [CategoryController::class, 'destroy']);

// Sub-category routes
Route::get('sub-categories', [SubCategoryController::class, 'index']);
Route::post('sub-categories', [SubCategoryController::class, 'store']);
Route::get('sub-categories/{id}', [SubCategoryController::class, 'show']);
Route::put('sub-categories/{id}', [SubCategoryController::class, 'update']);
Route::delete('sub-categories/{id}', [SubCategoryController::class, 'destroy']);

// Wishlist routes
Route::get('wishlist', [WishlistController::class, 'index']);
Route::post('wishlist', [WishlistController::class, 'store']);
Route::post('wishlist/toggle', [WishlistController::class, 'toggle']);
Route::delete('wishlist/{productId}', [WishlistController::class, 'destroy']);
Route::delete('wishlist', [WishlistController::class, 'clear']);

// Cart routes
Route::get('cart', [CartController::class, 'index']);
Route::post('cart', [CartController::class, 'store']);
Route::put('cart/{productId}', [CartController::class, 'update']);
Route::delete('cart/{productId}', [CartController::class, 'destroy']);
Route::delete('cart', [CartController::class, 'clear']);

// Order routes
Route::post('orders/confirm', [OrderController::class, 'confirmOrder']);

