<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\SubCategory;
use App\Services\PromotionService;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function __construct(private PromotionService $promotions)
    {
    }

    // Show a list of all products, each decorated with promotion pricing + stock.
    public function index()
    {
        $products = Product::with(['subCategory', 'stocks.store'])->get();

        $resolved = $this->promotions->resolveForProducts($products);

        $payload = $products->map(function (Product $product) use ($resolved) {
            return array_merge(
                $product->toArray(),
                $this->promotions->pricingPayload($product, $resolved[$product->id] ?? null),
                $product->availabilityPayload()
            );
        });

        return response()->json($payload);
    }

    // Create a new product
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string',
            'reference' => 'required|string|unique:products,reference',
            'sub_category_id' => 'required|exists:sub_categories,id', // Ensure sub-category exists
            'price' => 'required|numeric',
            'description' => 'required|string',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048', // Validate image
            'stock' => 'nullable|array',                 // { store_id: quantity }
            'stock.*' => 'nullable|integer|min:0',
        ]);

        // Handle the file upload for the image
        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $this->storeImage($request->file('image'));
        }

        // Create the product
        $product = Product::create([
            'name' => $request->name,
            'reference' => $request->reference,
            'sub_category_id' => $request->sub_category_id,
            'price' => $request->price,
            'description' => $request->description,
            'image_url' => $imagePath,
        ]);

        $this->syncStock($product, $request->input('stock', []));

        return response()->json([
            'message' => 'Product created successfully',
            'product' => $product->load('stocks.store'),
        ], 201);
    }

    // Show a specific product by ID, decorated with promotion pricing + stock.
    public function show($id)
    {
        $product = Product::with(['subCategory', 'stocks.store'])->findOrFail($id);
        $promotion = $this->promotions->resolveForProduct($product);

        return response()->json(array_merge(
            $product->toArray(),
            $this->promotions->pricingPayload($product, $promotion),
            $product->availabilityPayload()
        ));
    }

    // Update a product
    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'name' => 'required|string',
            'reference' => 'required|string|unique:products,reference,' . $id,
            'sub_category_id' => 'required|exists:sub_categories,id',
            'price' => 'required|numeric',
            'description' => 'required|string',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'stock' => 'nullable|array',
            'stock.*' => 'nullable|integer|min:0',
        ]);

        $product = Product::findOrFail($id);

        // Handle image update
        $imagePath = $product->image_url;
        if ($request->hasFile('image')) {
            // Delete the old image if it lives in our images directory
            $this->deleteImage($product->image_url);
            $imagePath = $this->storeImage($request->file('image'));
        }

        // Update the product
        $product->update([
            'name' => $request->name,
            'reference' => $request->reference,
            'sub_category_id' => $request->sub_category_id,
            'price' => $request->price,
            'description' => $request->description,
            'image_url' => $imagePath ? $imagePath : $product->image_url,
        ]);

        // Only touch stock if the admin sent a stock map (so an edit without it
        // doesn't wipe existing quantities).
        if ($request->has('stock')) {
            $this->syncStock($product, $request->input('stock', []));
        }

        return response()->json([
            'message' => 'Product updated successfully',
            'product' => $product->load('stocks.store'),
        ]);
    }

    // Delete a product
    public function destroy($id)
    {
        $product = Product::findOrFail($id);

        // Delete the image if it exists
        $this->deleteImage($product->image_url);

        $product->delete(); // product_stock rows cascade
        return response()->json(['message' => 'Product deleted successfully']);
    }

    /**
     * Upsert per-store stock from a { store_id: quantity } map. Only existing
     * stores are accepted; missing entries are left untouched.
     *
     * @param  array<int|string,mixed>  $stockMap
     */
    private function syncStock(Product $product, array $stockMap): void
    {
        if (empty($stockMap)) {
            return;
        }

        $validStoreIds = \App\Models\Store::pluck('id')->all();

        foreach ($stockMap as $storeId => $qty) {
            $storeId = (int) $storeId;
            if (! in_array($storeId, $validStoreIds, true)) {
                continue;
            }
            \App\Models\ProductStock::updateOrCreate(
                ['product_id' => $product->id, 'store_id' => $storeId],
                ['quantity' => max(0, (int) $qty)]
            );
        }
    }

    /**
     * Store an uploaded image under a random, server-generated filename.
     *
     * The original client filename is never used, which prevents path
     * traversal, overwriting existing files, and serving attacker-named files.
     */
    private function storeImage(\Illuminate\Http\UploadedFile $file): string
    {
        // Extension is taken from the validated MIME type, not the client name.
        $extension = $file->extension() ?: $file->getClientOriginalExtension();
        $filename = \Illuminate\Support\Str::random(40) . '.' . $extension;
        $file->move(public_path('images'), $filename);

        return '/images/' . $filename;
    }

    /**
     * Delete a previously stored image, guarding against path traversal so we
     * only ever unlink files inside public/images.
     */
    private function deleteImage(?string $imageUrl): void
    {
        if (! $imageUrl) {
            return;
        }

        $relative = ltrim($imageUrl, '/');
        $imagesDir = public_path('images') . DIRECTORY_SEPARATOR;
        $fullPath = public_path($relative);
        $realImagesDir = realpath(public_path('images'));
        $realFull = realpath($fullPath);

        // Only delete files that genuinely resolve inside public/images.
        if ($realImagesDir && $realFull && str_starts_with($realFull, $realImagesDir) && is_file($realFull)) {
            @unlink($realFull);
        }
    }
}
