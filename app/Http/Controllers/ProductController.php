<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\SubCategory;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    // Show a list of all products
    public function index()
    {
        $products = Product::with('subCategory')->get(); // Fetch products along with their sub-category
        return response()->json($products);
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
        ]);

        // Handle the file upload for the image
        $imagePath = null;
        if ($request->hasFile('image')) {
            $file = $request->file('image');
            $originalName = $file->getClientOriginalName();
            // Store in public/images directory with original filename
            $file->move(public_path('images'), $originalName);
            $imagePath = '/images/' . $originalName;
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

        return response()->json(['message' => 'Product created successfully', 'product' => $product], 201);
    }

    // Show a specific product by ID
    public function show($id)
    {
        $product = Product::with('subCategory')->findOrFail($id);
        return response()->json($product);
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
        ]);

        $product = Product::findOrFail($id);

        // Handle image update
        $imagePath = $product->image_url;
        if ($request->hasFile('image')) {
            // Delete the old image if exists
            if ($imagePath) {
                // Normalize path (remove leading slash if present for file operations)
                $oldPath = ltrim($imagePath, '/');
                if (file_exists(public_path($oldPath))) {
                    unlink(public_path($oldPath));
                }
            }
            // Store new image with original filename
            $file = $request->file('image');
            $originalName = $file->getClientOriginalName();
            $file->move(public_path('images'), $originalName);
            $imagePath = '/images/' . $originalName;
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

        return response()->json(['message' => 'Product updated successfully', 'product' => $product]);
    }

    // Delete a product
    public function destroy($id)
    {
        $product = Product::findOrFail($id);
        
        // Delete the image if it exists
        if ($product->image_url) {
            // Normalize path (remove leading slash if present for file operations)
            $imagePath = ltrim($product->image_url, '/');
            if (file_exists(public_path($imagePath))) {
                unlink(public_path($imagePath));
            }
        }

        $product->delete();
        return response()->json(['message' => 'Product deleted successfully']);
    }
}
