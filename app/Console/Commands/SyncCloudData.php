<?php

namespace App\Console\Commands;

use App\Models\CloudApplication;
use App\Models\CloudEnvironment;
use App\Models\UsageItem;
use App\Models\UsageSnapshot;
use App\Services\LaravelCloud;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Http\Client\HttpClientException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Number;

#[Signature('cloud:sync {--period=0 : The billing period offset (0 = current period, max 3)}')]
#[Description('Pull applications, environments, and usage data from Laravel Cloud')]
class SyncCloudData extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(LaravelCloud $cloud): int
    {
        if (blank(config('services.laravel_cloud.key'))) {
            $this->components->error('The LARAVEL_CLOUD_API_KEY environment variable is not set.');

            return self::FAILURE;
        }

        $period = (int) $this->option('period');

        if ($period < 0 || $period > 3) {
            $this->components->error('The --period option must be between 0 and 3.');

            return self::INVALID;
        }

        try {
            $applications = $this->syncApplications($cloud);
            $this->components->twoColumnDetail('Applications synced', (string) $applications->count());

            $environmentCount = $this->syncEnvironments($cloud, $applications);
            $this->components->twoColumnDetail('Environments synced', (string) $environmentCount);

            $snapshot = $this->syncUsage($cloud, $period);
            $this->components->twoColumnDetail('Usage items stored', (string) $snapshot->items()->count());

            $this->calculateBurnRates($snapshot);
            $this->components->twoColumnDetail('Burn rate', $snapshot->burn_rate_cents_per_hour === null
                ? 'n/a (no earlier cloud timestamp to compare against in this billing period)'
                : Number::currency($snapshot->burn_rate_cents_per_hour / 100, in: $snapshot->currency ?? 'USD').' / hour');
        } catch (HttpClientException $exception) {
            $this->components->error("Laravel Cloud API request failed: {$exception->getMessage()}");

            return self::FAILURE;
        }

        $this->components->info(sprintf(
            'Total spend for the synced billing period: %s',
            Number::currency($snapshot->current_spend_cents / 100, in: $snapshot->currency ?? 'USD'),
        ));

        return self::SUCCESS;
    }

    /**
     * Sync all Laravel Cloud applications and return the local records.
     *
     * @return Collection<int, CloudApplication>
     */
    protected function syncApplications(LaravelCloud $cloud): Collection
    {
        return collect($cloud->applications())->map(fn (array $application): CloudApplication => CloudApplication::updateOrCreate(
            ['cloud_id' => $application['id']],
            [
                'name' => data_get($application, 'attributes.name'),
                'slug' => data_get($application, 'attributes.slug'),
                'region' => data_get($application, 'attributes.region'),
                'repository' => data_get($application, 'attributes.repository.full_name'),
                'avatar_url' => data_get($application, 'attributes.avatar_url'),
                'cloud_created_at' => data_get($application, 'attributes.created_at'),
                'last_synced_at' => now(),
            ],
        ));
    }

    /**
     * Sync the environments of each application and return how many were synced.
     *
     * @param  Collection<int, CloudApplication>  $applications
     */
    protected function syncEnvironments(LaravelCloud $cloud, Collection $applications): int
    {
        return (int) $applications->sum(function (CloudApplication $application) use ($cloud): int {
            $environments = $cloud->environments($application->cloud_id);

            foreach ($environments as $environment) {
                CloudEnvironment::updateOrCreate(
                    ['cloud_id' => $environment['id']],
                    [
                        'cloud_application_id' => $application->id,
                        'name' => data_get($environment, 'attributes.name'),
                        'slug' => data_get($environment, 'attributes.slug'),
                        'status' => data_get($environment, 'attributes.status'),
                        'vanity_domain' => data_get($environment, 'attributes.vanity_domain'),
                        'php_major_version' => data_get($environment, 'attributes.php_major_version'),
                        'uses_octane' => (bool) data_get($environment, 'attributes.uses_octane'),
                        'uses_hibernation' => (bool) data_get($environment, 'attributes.uses_hibernation'),
                        'cloud_created_at' => data_get($environment, 'attributes.created_at'),
                        'last_synced_at' => now(),
                    ],
                );
            }

            return count($environments);
        });
    }

    /**
     * Store a usage snapshot with its line items for the given billing period.
     */
    protected function syncUsage(LaravelCloud $cloud, int $period): UsageSnapshot
    {
        $payload = $cloud->usage($period);

        $data = (array) ($payload['data'] ?? []);
        $meta = (array) ($payload['meta'] ?? []);

        return DB::transaction(function () use ($payload, $data, $meta, $period): UsageSnapshot {
            $applicationNames = CloudApplication::pluck('name', 'cloud_id');

            $snapshot = UsageSnapshot::create([
                'period' => $period,
                'period_from' => data_get($meta, "available_periods.{$period}.from"),
                'period_to' => data_get($meta, "available_periods.{$period}.to"),
                'currency' => data_get($meta, 'currency'),
                'current_spend_cents' => (int) data_get($data, 'summary.current_spend_cents', 0),
                'bandwidth_cost_cents' => data_get($data, 'summary.bandwidth.cost_cents'),
                'bandwidth_usage_percentage' => data_get($data, 'summary.bandwidth.usage_percentage'),
                'bandwidth_allowance_bytes' => data_get($data, 'summary.bandwidth.allowance_bytes'),
                'credits_used_cents' => data_get($data, 'summary.credits.used_cents'),
                'credits_total_cents' => data_get($data, 'summary.credits.total_cents'),
                'alert_threshold_cents' => data_get($data, 'summary.alert.threshold_cents'),
                'alert_remaining_percentage' => data_get($data, 'summary.alert.remaining_percentage'),
                'resources_cost_cents' => (int) data_get($data, 'resources.total_cost_cents', 0),
                'addons_cost_cents' => (int) data_get($data, 'addons.total_cost_cents', 0),
                'applications_cost_cents' => (int) data_get($data, 'application_totals.total_cost_cents', 0),
                'application_count' => (int) data_get($data, 'application_totals.application_count', 0),
                'cloud_updated_at' => data_get($meta, 'last_updated_at'),
                'raw' => $payload,
            ]);

            foreach ($this->usageItemsByCategory($data) as $category => $items) {
                foreach ($items as $item) {
                    if (! is_array($item)) {
                        continue;
                    }

                    $snapshot->items()->create([
                        'category' => $category,
                        'name' => $this->resolveItemName($item, $applicationNames),
                        'cost_cents' => $this->extractItemCostCents($item),
                        'payload' => $item,
                    ]);
                }
            }

            return $snapshot;
        });
    }

    /**
     * Calculate hourly burn rates by comparing the snapshot against the
     * previous snapshot of the same billing period, using the cloud_updated_at
     * timestamps reported by Laravel Cloud as the elapsed time.
     */
    protected function calculateBurnRates(UsageSnapshot $snapshot): void
    {
        $currentUpdatedAt = $snapshot->cloud_updated_at;

        if ($currentUpdatedAt === null) {
            return;
        }

        // Laravel Cloud refreshes last_updated_at coarsely, so several syncs can
        // share the same cloud timestamp. Compare against the latest snapshot of
        // the same billing period whose cloud timestamp is strictly older.
        $previous = UsageSnapshot::query()
            ->where('id', '<', $snapshot->id)
            ->where('period_from', $snapshot->period_from)
            ->where('cloud_updated_at', '<', $currentUpdatedAt)
            ->latest('id')
            ->first();

        $previousUpdatedAt = $previous?->cloud_updated_at;

        if ($previous === null || $previousUpdatedAt === null) {
            return;
        }

        $hours = $previousUpdatedAt->diffInHours($currentUpdatedAt);

        if ($hours <= 0) {
            return;
        }

        $snapshot->update([
            'burn_rate_cents_per_hour' => round(($snapshot->current_spend_cents - $previous->current_spend_cents) / $hours, 4),
        ]);

        $previousItems = $previous->items()
            ->where('category', 'application')
            ->get()
            ->keyBy(fn (UsageItem $item): string => $this->applicationIdentifier($item));

        foreach ($snapshot->items()->where('category', 'application')->get() as $item) {
            $previousItem = $previousItems->get($this->applicationIdentifier($item));

            if ($previousItem === null) {
                continue;
            }

            $item->update([
                'burn_rate_cents_per_hour' => round(($item->cost_cents - $previousItem->cost_cents) / $hours, 4),
            ]);
        }
    }

    /**
     * Identify an application usage item consistently across snapshots.
     */
    protected function applicationIdentifier(UsageItem $item): string
    {
        $identifier = data_get($item->payload, 'identifier');

        return is_string($identifier) ? $identifier : (string) $item->name;
    }

    /**
     * Map every usage breakdown list in the payload to a local item category.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, list<mixed>>
     */
    protected function usageItemsByCategory(array $data): array
    {
        return [
            'database' => array_values((array) data_get($data, 'resources.databases', [])),
            'cache' => array_values((array) data_get($data, 'resources.caches', [])),
            'bucket' => array_values((array) data_get($data, 'resources.buckets', [])),
            'websocket' => array_values((array) data_get($data, 'resources.websockets', [])),
            'addon' => array_values((array) data_get($data, 'addons.items', [])),
            'application' => array_values((array) data_get($data, 'application_totals.applications', [])),
            'environment' => array_values((array) data_get($data, 'environment_usage.items', [])),
        ];
    }

    /**
     * The usage item shapes are not documented, so probe common name keys and
     * fall back to the synced application record when the payload only
     * carries a cloud identifier (as application totals do).
     *
     * @param  array<string, mixed>  $item
     * @param  Collection<string, string>  $applicationNames
     */
    protected function resolveItemName(array $item, Collection $applicationNames): ?string
    {
        foreach (['name', 'label', 'title', 'slug'] as $key) {
            if (is_string($item[$key] ?? null) && $item[$key] !== '') {
                return $item[$key];
            }
        }

        $identifier = $item['identifier'] ?? null;

        return is_string($identifier) ? $applicationNames->get($identifier) : null;
    }

    /**
     * The usage item shapes are not documented, so probe common cost keys.
     *
     * @param  array<string, mixed>  $item
     */
    protected function extractItemCostCents(array $item): int
    {
        foreach (['total_cents', 'cost_cents', 'total_cost_cents', 'amount_cents'] as $key) {
            if (is_numeric($item[$key] ?? null)) {
                return (int) $item[$key];
            }
        }

        return 0;
    }
}
