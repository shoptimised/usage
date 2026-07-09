<?php

namespace Database\Factories;

use App\Models\UsageItem;
use App\Models\UsageSnapshot;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UsageItem>
 */
class UsageItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'usage_snapshot_id' => UsageSnapshot::factory(),
            'category' => fake()->randomElement(['database', 'cache', 'bucket', 'websocket', 'addon', 'application']),
            'name' => fake()->words(2, true),
            'cost_cents' => fake()->numberBetween(0, 50_000),
            'payload' => [],
        ];
    }
}
