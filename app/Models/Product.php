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

    /** Per-store stock rows. */
    public function stocks()
    {
        return $this->hasMany(ProductStock::class);
    }

    /** Stores carrying this product (with pivot quantity). */
    public function stores()
    {
        return $this->belongsToMany(Store::class, 'product_stock')
            ->withPivot('quantity')
            ->withTimestamps();
    }

    /** Total quantity across all stores. */
    public function totalStock(): int
    {
        return (int) $this->stocks->sum('quantity');
    }

    public function inStock(): bool
    {
        return $this->totalStock() > 0;
    }

    /**
     * Availability payload for API output: total + per-store breakdown, and the
     * names of stores that currently have it.
     *
     * @return array{total_stock:int, in_stock:bool, by_store:array, available_stores:array}
     */
    public function availabilityPayload(): array
    {
        $byStore = $this->stocks->map(fn (ProductStock $s) => [
            'store_id' => $s->store_id,
            'store' => $s->store?->name,
            'city' => $s->store?->city,
            'quantity' => (int) $s->quantity,
        ])->values();

        $available = $byStore->filter(fn ($s) => $s['quantity'] > 0)
            ->pluck('city')
            ->values();

        return [
            'total_stock' => (int) $byStore->sum('quantity'),
            'in_stock' => $byStore->sum('quantity') > 0,
            'by_store' => $byStore,
            'available_stores' => $available,
        ];
    }
}
