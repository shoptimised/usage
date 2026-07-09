<?php

use App\Models\CloudApplication;
use App\Models\CloudEnvironment;
use App\Models\UsageItem;
use App\Models\UsageSnapshot;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config()->set('services.laravel_cloud.key', 'test-token');

    Http::preventStrayRequests();
});

test('cloud sync pulls and stores applications, environments, and usage', function () {
    Http::fake([
        'cloud.laravel.com/api/applications/*/environments*' => Http::response(cloudEnvironmentsPayload()),
        'cloud.laravel.com/api/applications*' => Http::response(cloudApplicationsPayload()),
        'cloud.laravel.com/api/usage*' => Http::response(cloudUsagePayload()),
    ]);

    $this->artisan('cloud:sync')->assertSuccessful();

    Http::assertSent(fn (Request $request): bool => $request->hasHeader('Authorization', 'Bearer test-token'));

    $application = CloudApplication::sole();
    expect($application->cloud_id)->toBe('app-1')
        ->and($application->name)->toBe('Usage Tracker')
        ->and($application->slug)->toBe('usage-tracker')
        ->and($application->region)->toBe('eu-west-2')
        ->and($application->repository)->toBe('acme/usage-tracker')
        ->and($application->last_synced_at)->not->toBeNull();

    expect(CloudEnvironment::count())->toBe(2);

    $production = CloudEnvironment::where('slug', 'production')->sole();
    expect($production->cloudApplication->is($application))->toBeTrue()
        ->and($production->status)->toBe('running')
        ->and($production->php_major_version)->toBe('8.4')
        ->and($production->uses_octane)->toBeTrue()
        ->and($production->uses_hibernation)->toBeFalse();

    $snapshot = UsageSnapshot::sole();
    expect($snapshot->period)->toBe(0)
        ->and($snapshot->currency)->toBe('USD')
        ->and($snapshot->current_spend_cents)->toBe(12345)
        ->and($snapshot->bandwidth_usage_percentage)->toBe(12)
        ->and($snapshot->bandwidth_allowance_bytes)->toBe(1000000000000)
        ->and($snapshot->credits_used_cents)->toBe(500)
        ->and($snapshot->resources_cost_cents)->toBe(4200)
        ->and($snapshot->addons_cost_cents)->toBe(900)
        ->and($snapshot->applications_cost_cents)->toBe(7245)
        ->and($snapshot->application_count)->toBe(1)
        ->and($snapshot->period_from->toDateString())->toBe('2026-06-15')
        ->and($snapshot->period_to->toDateString())->toBe('2026-07-15')
        ->and($snapshot->raw)->toHaveKeys(['data', 'meta']);

    expect($snapshot->items()->orderBy('id')->pluck('cost_cents', 'name')->all())->toBe([
        'primary-db' => 3000,
        'main-cache' => 1200,
        'Compute add-on' => 900,
        'Usage Tracker' => 7245,
    ]);

    $databaseItem = UsageItem::where('category', 'database')->sole();
    expect($databaseItem->payload)->toBe(['name' => 'primary-db', 'total_cents' => 3000]);
});

test('cloud sync follows pagination when listing applications', function () {
    Http::fake([
        'cloud.laravel.com/api/applications/*/environments*' => Http::response(['data' => [], 'links' => ['next' => null], 'meta' => []]),
        'cloud.laravel.com/api/applications?page=2*' => Http::response(cloudApplicationsPayload(id: 'app-2')),
        'cloud.laravel.com/api/applications*' => Http::response(cloudApplicationsPayload(next: 'https://cloud.laravel.com/api/applications?page=2')),
        'cloud.laravel.com/api/usage*' => Http::response(cloudUsagePayload()),
    ]);

    $this->artisan('cloud:sync')->assertSuccessful();

    expect(CloudApplication::pluck('cloud_id')->all())->toBe(['app-1', 'app-2']);
});

test('cloud sync does not duplicate applications or environments when run twice', function () {
    Http::fake([
        'cloud.laravel.com/api/applications/*/environments*' => Http::response(cloudEnvironmentsPayload()),
        'cloud.laravel.com/api/applications*' => Http::response(cloudApplicationsPayload()),
        'cloud.laravel.com/api/usage*' => Http::response(cloudUsagePayload()),
    ]);

    $this->artisan('cloud:sync')->assertSuccessful();
    $this->artisan('cloud:sync')->assertSuccessful();

    expect(CloudApplication::count())->toBe(1)
        ->and(CloudEnvironment::count())->toBe(2)
        ->and(UsageSnapshot::count())->toBe(2)
        ->and(UsageItem::count())->toBe(8);
});

test('cloud sync fails when the api key is not configured', function () {
    config()->set('services.laravel_cloud.key', null);

    $this->artisan('cloud:sync')
        ->expectsOutputToContain('LARAVEL_CLOUD_API_KEY')
        ->assertFailed();
});

test('cloud sync rejects an out of range period', function () {
    $this->artisan('cloud:sync', ['--period' => '9'])->assertFailed();
});

test('cloud sync fails gracefully when the api returns an error', function () {
    Http::fake([
        '*' => Http::response(['message' => 'Unauthenticated.'], 401),
    ]);

    $this->artisan('cloud:sync')->assertFailed();

    expect(CloudApplication::count())->toBe(0)
        ->and(UsageSnapshot::count())->toBe(0);
});

test('cloud sync calculates hourly burn rates against the previous snapshot of the same period', function () {
    Http::fake([
        'cloud.laravel.com/api/applications/*/environments*' => Http::response(cloudEnvironmentsPayload()),
        'cloud.laravel.com/api/applications*' => Http::response(cloudApplicationsPayload()),
        'cloud.laravel.com/api/usage*' => Http::sequence()
            ->push(cloudUsagePayload())
            ->push(cloudUsagePayload(
                currentSpendCents: 12945,
                applicationCostCents: 7545,
                lastUpdatedAt: '2026-07-09T10:00:00.000000Z',
            )),
    ]);

    $this->artisan('cloud:sync')->assertSuccessful();
    $this->artisan('cloud:sync')->assertSuccessful();

    $first = UsageSnapshot::oldest('id')->firstOrFail();
    $latest = UsageSnapshot::latest('id')->firstOrFail();

    // 600 spend cents and 300 application cents accrued over the 2 hours between cloud timestamps.
    expect($first->burn_rate_cents_per_hour)->toBeNull()
        ->and($latest->burn_rate_cents_per_hour)->toBe(300.0)
        ->and($latest->items()->where('category', 'application')->sole()->burn_rate_cents_per_hour)->toBe(150.0)
        ->and($latest->items()->where('category', 'database')->sole()->burn_rate_cents_per_hour)->toBeNull();
});

test('cloud sync skips burn rates when the cloud usage timestamp has not advanced', function () {
    Http::fake([
        'cloud.laravel.com/api/applications/*/environments*' => Http::response(cloudEnvironmentsPayload()),
        'cloud.laravel.com/api/applications*' => Http::response(cloudApplicationsPayload()),
        'cloud.laravel.com/api/usage*' => Http::sequence()
            ->push(cloudUsagePayload())
            ->push(cloudUsagePayload(currentSpendCents: 13000)),
    ]);

    $this->artisan('cloud:sync')->assertSuccessful();
    $this->artisan('cloud:sync')->assertSuccessful();

    expect(UsageSnapshot::latest('id')->firstOrFail()->burn_rate_cents_per_hour)->toBeNull();
});

test('cloud sync compares against the latest snapshot with an older cloud timestamp', function () {
    Http::fake([
        'cloud.laravel.com/api/applications/*/environments*' => Http::response(cloudEnvironmentsPayload()),
        'cloud.laravel.com/api/applications*' => Http::response(cloudApplicationsPayload()),
        'cloud.laravel.com/api/usage*' => Http::sequence()
            ->push(cloudUsagePayload())
            ->push(cloudUsagePayload(currentSpendCents: 12545))
            ->push(cloudUsagePayload(
                currentSpendCents: 13145,
                lastUpdatedAt: '2026-07-09T10:00:00.000000Z',
            )),
    ]);

    $this->artisan('cloud:sync')->assertSuccessful();
    $this->artisan('cloud:sync')->assertSuccessful();
    $this->artisan('cloud:sync')->assertSuccessful();

    // The third snapshot diffs against the second (same cloud timestamp as the
    // first but fresher costs): (13145 - 12545) / 2 hours, not (13145 - 12345).
    expect(UsageSnapshot::latest('id')->firstOrFail()->burn_rate_cents_per_hour)->toBe(300.0);
});

test('cloud sync skips burn rates when the billing period has changed', function () {
    Http::fake([
        'cloud.laravel.com/api/applications/*/environments*' => Http::response(cloudEnvironmentsPayload()),
        'cloud.laravel.com/api/applications*' => Http::response(cloudApplicationsPayload()),
        'cloud.laravel.com/api/usage*' => Http::sequence()
            ->push(cloudUsagePayload())
            ->push(cloudUsagePayload(
                currentSpendCents: 500,
                lastUpdatedAt: '2026-07-16T02:00:00.000000Z',
                periodFrom: '2026-07-15T00:00:00.000000Z',
            )),
    ]);

    $this->artisan('cloud:sync')->assertSuccessful();
    $this->artisan('cloud:sync')->assertSuccessful();

    expect(UsageSnapshot::latest('id')->firstOrFail()->burn_rate_cents_per_hour)->toBeNull();
});

/**
 * @return array<string, mixed>
 */
function cloudApplicationsPayload(string $id = 'app-1', ?string $next = null): array
{
    return [
        'data' => [
            [
                'id' => $id,
                'type' => 'applications',
                'attributes' => [
                    'name' => 'Usage Tracker',
                    'slug' => 'usage-tracker',
                    'region' => 'eu-west-2',
                    'slack_channel' => null,
                    'avatar_url' => 'https://cloud.laravel.com/avatars/'.$id.'.png',
                    'created_at' => '2026-01-15T12:00:00.000000Z',
                    'repository' => [
                        'full_name' => 'acme/usage-tracker',
                        'default_branch' => 'main',
                    ],
                ],
            ],
        ],
        'links' => ['first' => null, 'last' => null, 'prev' => null, 'next' => $next],
        'meta' => ['current_page' => 1, 'last_page' => $next === null ? 1 : 2, 'total' => 1],
    ];
}

/**
 * @return array<string, mixed>
 */
function cloudEnvironmentsPayload(): array
{
    return [
        'data' => [
            [
                'id' => 'env-1',
                'type' => 'environments',
                'attributes' => [
                    'name' => 'Production',
                    'slug' => 'production',
                    'status' => 'running',
                    'vanity_domain' => 'usage-tracker-prod.laravel.cloud',
                    'php_major_version' => '8.4',
                    'uses_octane' => true,
                    'uses_hibernation' => false,
                    'created_at' => '2026-01-15T12:05:00.000000Z',
                ],
            ],
            [
                'id' => 'env-2',
                'type' => 'environments',
                'attributes' => [
                    'name' => 'Staging',
                    'slug' => 'staging',
                    'status' => 'hibernating',
                    'vanity_domain' => 'usage-tracker-staging.laravel.cloud',
                    'php_major_version' => '8.3',
                    'uses_octane' => false,
                    'uses_hibernation' => true,
                    'created_at' => '2026-02-01T09:00:00.000000Z',
                ],
            ],
        ],
        'links' => ['first' => null, 'last' => null, 'prev' => null, 'next' => null],
        'meta' => ['current_page' => 1, 'last_page' => 1, 'total' => 2],
    ];
}

/**
 * @return array<string, mixed>
 */
function cloudUsagePayload(
    int $currentSpendCents = 12345,
    int $applicationCostCents = 7245,
    string $lastUpdatedAt = '2026-07-09T08:00:00.000000Z',
    string $periodFrom = '2026-06-15T00:00:00.000000Z',
): array {
    return [
        'data' => [
            'summary' => [
                'current_spend_cents' => $currentSpendCents,
                'bandwidth' => [
                    'cost_cents' => 0,
                    'usage_percentage' => 12,
                    'allowance_bytes' => 1000000000000,
                ],
                'credits' => [
                    'used_cents' => 500,
                    'total_cents' => 1000,
                ],
                'alert' => [
                    'threshold_cents' => 20000,
                    'remaining_percentage' => 38,
                ],
            ],
            'resources' => [
                'total_cost_cents' => 4200,
                'databases' => [
                    ['name' => 'primary-db', 'total_cents' => 3000],
                ],
                'caches' => [
                    ['name' => 'main-cache', 'total_cents' => 1200],
                ],
                'buckets' => [],
                'websockets' => [],
            ],
            'addons' => [
                'total_cost_cents' => 900,
                'items' => [
                    ['name' => 'Compute add-on', 'total_cents' => 900],
                ],
            ],
            'application_totals' => [
                'total_cost_cents' => $applicationCostCents,
                'application_count' => 1,
                'applications' => [
                    ['deleted' => false, 'identifier' => 'app-1', 'total_cost_cents' => $applicationCostCents, 'environment_count' => 2],
                ],
            ],
            'environment_usage' => null,
            'private_cloud' => null,
        ],
        'meta' => [
            'currency' => 'USD',
            'period' => 0,
            'available_periods' => [
                ['from' => $periodFrom, 'to' => '2026-07-15T00:00:00.000000Z'],
            ],
            'last_updated_at' => $lastUpdatedAt,
        ],
    ];
}
