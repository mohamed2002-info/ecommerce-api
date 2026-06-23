<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Store extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'city', 'slug'];

    public function products()
    {
        return $this->belongsToMany(Product::class, 'product_stock')
            ->withPivot('quantity')
            ->withTimestamps();
    }
}
