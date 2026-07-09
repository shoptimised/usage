<?php

namespace Database\Factories;

use App\Models\UsageSnapshot;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UsageSnapshot>
 */
class UsageSnapshotFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'period' => 0,
            'period_from' => now()->subDays(15),
            'period_to' => now()->addDays(15),
            'currency' => 'USD',
            'current_spend_cents' => fake()->numberBetween(1_000, 250_000),
            'bandwidth_cost_cents' => 0,
            'bandwidth_usage_percentage' => fake()->numberBetween(0, 100),
            'bandwidth_allowance_bytes' => 1_099_511_627_776,
            'credits_used_cents' => 0,
            'credits_total_cents' => 0,
            'alert_threshold_cents' => null,
            'alert_remaining_percentage' => null,
            'resources_cost_cents' => fake()->numberBetween(0, 100_000),
            'addons_cost_cents' => fake()->numberBetween(0, 25_000),
            'applications_cost_cents' => fake()->numberBetween(0, 100_000),
            'application_count' => fake()->numberBetween(1, 10),
            'cloud_updated_at' => now(),
            'raw' => [],
        ];
    }
}
