<?php

namespace Database\Factories;

use App\Models\CloudApplication;
use App\Models\CloudEnvironment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CloudEnvironment>
 */
class CloudEnvironmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->randomElement(['production', 'staging', 'development', 'preview']);

        return [
            'cloud_application_id' => CloudApplication::factory(),
            'cloud_id' => fake()->uuid(),
            'name' => $name,
            'slug' => $name,
            'status' => fake()->randomElement(['deploying', 'running', 'hibernating', 'stopped']),
            'vanity_domain' => fake()->domainName(),
            'php_major_version' => fake()->randomElement(['8.2', '8.3', '8.4', '8.5']),
            'uses_octane' => fake()->boolean(),
            'uses_hibernation' => fake()->boolean(),
            'cloud_created_at' => fake()->dateTimeBetween('-1 year'),
            'last_synced_at' => now(),
        ];
    }
}
