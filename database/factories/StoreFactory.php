<?php

namespace Database\Factories;

use App\Models\Store;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class StoreFactory extends Factory
{
    protected $model = Store::class;

    public function definition(): array
    {
        $city = fake()->unique()->city();

        return [
            'name' => "Boutique {$city}",
            'city' => $city,
            'slug' => Str::slug($city) . '-' . Str::random(4),
        ];
    }
}
