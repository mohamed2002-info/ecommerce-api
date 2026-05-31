<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $table = 'products';

    protected $fillable = [
        'name',
        'reference',
        'sub_category_id',
        'price',
        'description',
        'image_url',
    ];

    public function subCategory()
    {
        return $this->belongsTo(SubCategory::class);
    }

}
