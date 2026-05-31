<?php

namespace App\Http\Controllers;

use App\Models\SubCategory;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SubCategoryController extends Controller
{
    // Show a list of all sub-categories
    public function index()
    {
        $subCategories = SubCategory::with('category')->get();
        return response()->json($subCategories);
    }

    // Create a new sub-category
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string',
            'category_id' => 'required|exists:categories,id',
        ]);

        $subCategory = SubCategory::create([
            'name' => $request->name,
            'category_id' => $request->category_id,
            'slug' => Str::slug($request->name),
        ]);

        return response()->json(['message' => 'Sub-category created successfully', 'sub_category' => $subCategory], 201);
    }

    // Show a specific sub-category by ID
    public function show($id)
    {
        $subCategory = SubCategory::with('category')->findOrFail($id);
        return response()->json($subCategory);
    }

    // Update a sub-category
    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'name' => 'required|string',
            'category_id' => 'required|exists:categories,id',
        ]);

        $subCategory = SubCategory::findOrFail($id);
        $subCategory->update([
            'name' => $request->name,
            'category_id' => $request->category_id,
            'slug' => Str::slug($request->name),
        ]);

        return response()->json(['message' => 'Sub-category updated successfully', 'sub_category' => $subCategory]);
    }

    // Delete a sub-category
    public function destroy($id)
    {
        $subCategory = SubCategory::findOrFail($id);
        
        // Check if sub-category has products
        $productCount = Product::where('sub_category_id', $id)->count();
        
        if ($productCount > 0) {
            return response()->json([
                'message' => "Cannot delete sub-category. It contains {$productCount} product(s). Please delete or move the products first.",
                'error' => 'subcategory_has_products',
                'product_count' => $productCount
            ], 422);
        }
        
        $subCategory->delete();

        return response()->json(['message' => 'Sub-category deleted successfully']);
    }
}

