<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class Promotion extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'target_type',
        'category_id',
        'discount_type',
        'value',
        'starts_at',
        'ends_at',
        'priority',
        'status',
        'max_uses',
        'audience',
        'auto_random_weekly',
    ];

    protected $casts = [
        'value' => 'decimal:2',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'priority' => 'integer',
        'max_uses' => 'integer',
        'uses_count' => 'integer',
        'auto_random_weekly' => 'boolean',
    ];

    public function products()
    {
        return $this->belongsToMany(Product::class, 'promotion_product');
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Scope: promotions that are currently live (active status and within the
     * scheduling window). Null start/end means "open-ended" on that side.
     */
    public function scopeLive(Builder $query, ?Carbon $now = null): Builder
    {
        $now = $now ?? Carbon::now();

        return $query->where('status', 'active')
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now))
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', $now));
    }

    /**
     * Is this promotion live at the given moment?
     */
    public function isLive(?Carbon $now = null): bool
    {
        $now = $now ?? Carbon::now();

        if ($this->status !== 'active') {
            return false;
        }
        if ($this->starts_at && $this->starts_at->gt($now)) {
            return false;
        }
        if ($this->ends_at && $this->ends_at->lt($now)) {
            return false;
        }

        return true;
    }

    /**
     * Apply this promotion's discount to a base price, returning the new price.
     * Never returns below zero. Only percentage/fixed are computed here; other
     * types are treated as no-op until implemented (phase 2).
     */
    public function discountedPrice(float $basePrice): float
    {
        $price = match ($this->discount_type) {
            'percentage' => $basePrice - ($basePrice * ((float) $this->value / 100)),
            'fixed' => $basePrice - (float) $this->value,
            default => $basePrice,
        };

        return round(max(0, $price), 2);
    }
}
