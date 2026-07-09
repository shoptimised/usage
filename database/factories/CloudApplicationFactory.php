<?php

namespace Database\Factories;

use App\Models\CloudApplication;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CloudApplication>
 */
class CloudApplicationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'cloud_id' => fake()->uuid(),
            'name' => fake()->words(2, true),
            'slug' => fake()->slug(2),
            'region' => fake()->randomElement(['us-east-1', 'us-east-2', 'eu-west-1', 'eu-west-2', 'eu-central-1']),
            'repository' => fake()->userName().'/'.fake()->slug(2),
            'avatar_url' => fake()->url(),
            'cloud_created_at' => fake()->dateTimeBetween('-1 year'),
            'last_synced_at' => now(),
        ];
    }
}
