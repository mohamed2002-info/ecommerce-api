<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SubCategory extends Model
{
    use HasFactory;

    protected $fillable = [
        'category_id',   // Foreign key to category
        'name',          // Sub-category name
        'slug',          // Slug for sub-category
    ];

    // Relationship with Category
    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    // Relationship with Products
    public function products()
    {
        return $this->hasMany(Product::class);
    }
}
