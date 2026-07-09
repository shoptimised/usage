<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        $email = config('seed.test_user.email');
        $password = config('seed.test_user.password');

        if (blank($email) || blank($password)) {
            $this->command->warn('TEST_USER_EMAIL / TEST_USER_PASSWORD are not set — skipping test user seed.');

            return;
        }

        User::updateOrCreate(
            ['email' => $email],
            [
                'name' => 'Test User',
                'password' => Hash::make($password),
                'email_verified_at' => now(),
            ],
        );
    }
}
