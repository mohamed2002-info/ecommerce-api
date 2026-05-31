<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\SubCategory;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CategoryController extends Controller
{
    // Show a list of all categories
    public function index()
    {
        $categories = Category::with('subCategories')->get(); // Retrieve categories and their sub-categories
        return response()->json($categories);
    }

    // Create a new category
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|unique:categories,name',
        ]);

        $category = Category::create([
            'name' => $request->name,
            'slug' => Str::slug($request->name),
        ]);

        return response()->json(['message' => 'Category created successfully', 'category' => $category], 201);
    }

    // Show a specific category by ID
    public function show($id)
    {
        $category = Category::with('subCategories')->findOrFail($id);
        return response()->json($category);
    }

    // Update a category
    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'name' => 'required|string|unique:categories,name,' . $id,
        ]);

        $category = Category::findOrFail($id);
        $category->update([
            'name' => $request->name,
            'slug' => Str::slug($request->name),
        ]);

        return response()->json(['message' => 'Category updated successfully', 'category' => $category]);
    }

    // Delete a category
    public function destroy($id)
    {
        $category = Category::findOrFail($id);
        
        // Check if category has sub-categories
        $subCategoryCount = SubCategory::where('category_id', $id)->count();
        
        if ($subCategoryCount > 0) {
            // Check if any sub-categories have products
            $subCategoryIds = SubCategory::where('category_id', $id)->pluck('id');
            $productCount = Product::whereIn('sub_category_id', $subCategoryIds)->count();
            
            if ($productCount > 0) {
                return response()->json([
                    'message' => "Cannot delete category. It contains {$subCategoryCount} sub-category(ies) with {$productCount} product(s). Please delete or move the products and sub-categories first.",
                    'error' => 'category_has_items',
                    'sub_category_count' => $subCategoryCount,
                    'product_count' => $productCount
                ], 422);
            }
            
            return response()->json([
                'message' => "Cannot delete category. It contains {$subCategoryCount} sub-category(ies). Please delete the sub-categories first.",
                'error' => 'category_has_subcategories',
                'sub_category_count' => $subCategoryCount
            ], 422);
        }
        
        $category->delete();

        return response()->json(['message' => 'Category deleted successfully']);
    }
}
