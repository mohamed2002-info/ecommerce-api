<?php

namespace Database\Factories;

use App\Models\Promotion;
use Illuminate\Database\Eloquent\Factories\Factory;

class PromotionFactory extends Factory
{
    protected $model = Promotion::class;

    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true),
            'description' => fake()->sentence(),
            'target_type' => 'products',
            'category_id' => null,
            'discount_type' => 'percentage',
            'value' => 20,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
            'priority' => 0,
            'status' => 'active',
            'audience' => 'all',
        ];
    }

    public function percentage(float $value): static
    {
        return $this->state(fn () => ['discount_type' => 'percentage', 'value' => $value]);
    }

    public function fixed(float $value): static
    {
        return $this->state(fn () => ['discount_type' => 'fixed', 'value' => $value]);
    }

    public function category(int $categoryId): static
    {
        return $this->state(fn () => ['target_type' => 'category', 'category_id' => $categoryId]);
    }

    public function all(): static
    {
        return $this->state(fn () => ['target_type' => 'all']);
    }

    public function paused(): static
    {
        return $this->state(fn () => ['status' => 'paused']);
    }

    public function expired(): static
    {
        return $this->state(fn () => [
            'starts_at' => now()->subWeek(),
            'ends_at' => now()->subDay(),
        ]);
    }

    public function future(): static
    {
        return $this->state(fn () => [
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addWeek(),
        ]);
    }
}
