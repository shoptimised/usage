<?php

use App\Models\CloudApplication;
use App\Models\CloudEnvironment;
use App\Models\UsageItem;
use App\Models\UsageSnapshot;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
});

test('dashboard reports no usage data before the first sync', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('usage', null));
});

test('dashboard shows the latest usage snapshot', function () {
    $this->actingAs(User::factory()->create());

    $application = CloudApplication::factory()->create([
        'cloud_id' => 'app-123',
        'name' => 'usage-tracker',
        'region' => 'eu-west-2',
    ]);

    CloudEnvironment::factory()->create([
        'cloud_application_id' => $application->id,
        'name' => 'Production',
        'status' => 'running',
    ]);

    UsageSnapshot::factory()->create(['current_spend_cents' => 111]);

    $snapshot = UsageSnapshot::factory()->create([
        'current_spend_cents' => 741340,
        'applications_cost_cents' => 357772,
        'application_count' => 17,
        'currency' => 'USD',
        'burn_rate_cents_per_hour' => 925.5,
    ]);

    UsageItem::factory()->create([
        'usage_snapshot_id' => $snapshot->id,
        'category' => 'application',
        'name' => 'usage-tracker',
        'cost_cents' => 277487,
        'burn_rate_cents_per_hour' => 450.25,
        'payload' => ['identifier' => 'app-123', 'deleted' => false, 'total_cost_cents' => 277487],
    ]);

    UsageItem::factory()->create([
        'usage_snapshot_id' => $snapshot->id,
        'category' => 'database',
        'name' => 'reporting',
        'cost_cents' => 443,
        'payload' => ['type' => 'Laravel MySQL 8.4'],
    ]);

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('usage.summary.current_spend_cents', 741340)
            ->where('usage.summary.application_count', 17)
            ->where('usage.summary.burn_rate_cents_per_hour', 925.5)
            ->where('usage.currency', 'USD')
            ->has('usage.applications', 1)
            ->where('usage.applications.0.name', 'usage-tracker')
            ->where('usage.applications.0.cost_cents', 277487)
            ->where('usage.applications.0.burn_rate_cents_per_hour', 450.25)
            ->where('usage.applications.0.region', 'eu-west-2')
            ->where('usage.applications.0.deleted', false)
            ->has('usage.applications.0.environments', 1)
            ->where('usage.applications.0.environments.0.status', 'running')
            ->has('usage.resources', 1)
            ->where('usage.resources.0.category', 'database')
            ->where('usage.resources.0.total_cents', 443)
            ->where('usage.resources.0.items.0.type', 'Laravel MySQL 8.4'));
});
