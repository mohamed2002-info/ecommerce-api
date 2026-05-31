<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    use HasFactory;

    // Define the table name (optional if table name matches the plural of model name)
    protected $table = 'categories';

    // Define the fillable fields (to allow mass assignment)
    protected $fillable = [
        'name',   // Category name
        'slug',   // Slug for category (used in URLs)
    ];

    // Relationship with sub-categories (if needed)
    public function subCategories()
    {
        return $this->hasMany(SubCategory::class);
    }

    // Relationship with products (if needed)
    public function products()
    {
        return $this->hasManyThrough(Product::class, SubCategory::class);
    }
}
