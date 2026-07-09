<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

test('database seeder creates the test user from configured credentials', function () {
    config()->set('seed.test_user.email', 'david@shoptimised.com');
    config()->set('seed.test_user.password', 'shop2025');

    $this->seed();

    $user = User::sole();
    expect($user->email)->toBe('david@shoptimised.com')
        ->and($user->name)->toBe('Test User')
        ->and($user->email_verified_at)->not->toBeNull()
        ->and(Hash::check('shop2025', $user->password))->toBeTrue();
});

test('database seeder skips the test user when credentials are not configured', function () {
    config()->set('seed.test_user.email', null);
    config()->set('seed.test_user.password', null);

    $this->seed();

    expect(User::count())->toBe(0);
});

test('database seeder does not duplicate the test user when run twice', function () {
    config()->set('seed.test_user.email', 'david@shoptimised.com');
    config()->set('seed.test_user.password', 'shop2025');

    $this->seed();
    $this->seed();

    expect(User::count())->toBe(1);
});

test('database seeder updates the test user password when credentials change', function () {
    config()->set('seed.test_user.email', 'david@shoptimised.com');
    config()->set('seed.test_user.password', 'shop2025');
    $this->seed();

    config()->set('seed.test_user.password', 'new-password');
    $this->seed();

    $user = User::sole();
    expect(Hash::check('new-password', $user->password))->toBeTrue();
});
