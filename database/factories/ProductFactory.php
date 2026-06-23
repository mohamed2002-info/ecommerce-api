<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\SubCategory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true),
            'reference' => 'REF-' . Str::upper(Str::random(8)),
            'sub_category_id' => SubCategory::factory(),
            'price' => fake()->randomFloat(2, 1, 999),
            'description' => fake()->sentence(),
            'image_url' => null,
        ];
    }
}
