<?php

namespace App\Http\Controllers;

use App\Models\CloudApplication;
use App\Models\CloudEnvironment;
use App\Models\UsageItem;
use App\Models\UsageSnapshot;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Display the latest Laravel Cloud usage data.
     */
    public function __invoke(): Response
    {
        $snapshot = UsageSnapshot::query()->with('items')->latest('id')->first();

        return Inertia::render('Dashboard', [
            'usage' => $snapshot === null ? null : [
                'synced_at' => $snapshot->created_at?->toIso8601String(),
                'cloud_updated_at' => $snapshot->cloud_updated_at?->toIso8601String(),
                'period' => [
                    'offset' => $snapshot->period,
                    'from' => $snapshot->period_from?->toIso8601String(),
                    'to' => $snapshot->period_to?->toIso8601String(),
                ],
                'currency' => $snapshot->currency ?? 'USD',
                'summary' => [
                    'current_spend_cents' => $snapshot->current_spend_cents,
                    'resources_cost_cents' => $snapshot->resources_cost_cents,
                    'addons_cost_cents' => $snapshot->addons_cost_cents,
                    'applications_cost_cents' => $snapshot->applications_cost_cents,
                    'application_count' => $snapshot->application_count,
                    'burn_rate_cents_per_hour' => $snapshot->burn_rate_cents_per_hour,
                    'bandwidth' => $snapshot->bandwidth_usage_percentage === null && $snapshot->bandwidth_allowance_bytes === null ? null : [
                        'cost_cents' => $snapshot->bandwidth_cost_cents,
                        'usage_percentage' => $snapshot->bandwidth_usage_percentage,
                        'allowance_bytes' => $snapshot->bandwidth_allowance_bytes,
                    ],
                    'credits' => $snapshot->credits_total_cents === null ? null : [
                        'used_cents' => $snapshot->credits_used_cents,
                        'total_cents' => $snapshot->credits_total_cents,
                    ],
                    'alert' => $snapshot->alert_threshold_cents === null ? null : [
                        'threshold_cents' => $snapshot->alert_threshold_cents,
                        'remaining_percentage' => $snapshot->alert_remaining_percentage,
                    ],
                ],
                'applications' => $this->applicationCosts($snapshot),
                'resources' => $this->resourceBreakdown($snapshot),
            ],
        ]);
    }

    /**
     * Combine the snapshot's per-application costs with the synced application records.
     *
     * @return list<array<string, mixed>>
     */
    protected function applicationCosts(UsageSnapshot $snapshot): array
    {
        $applications = CloudApplication::query()
            ->with('environments')
            ->get()
            ->keyBy('cloud_id');

        $costs = $snapshot->items
            ->where('category', 'application')
            ->map(function (UsageItem $item) use ($applications): array {
                $application = $applications->get((string) data_get($item->payload, 'identifier'));

                return [
                    'name' => $item->name ?? (string) data_get($item->payload, 'identifier', 'Unknown application'),
                    'cost_cents' => $item->cost_cents,
                    'burn_rate_cents_per_hour' => $item->burn_rate_cents_per_hour,
                    'deleted' => (bool) data_get($item->payload, 'deleted', false),
                    'region' => $application?->region,
                    'repository' => $application?->repository,
                    'avatar_url' => $application?->avatar_url,
                    'environments' => $application?->environments->sortBy('name')->map(fn (CloudEnvironment $environment): array => [
                        'name' => $environment->name,
                        'status' => $environment->status,
                        'php_major_version' => $environment->php_major_version,
                        'uses_octane' => $environment->uses_octane,
                    ])->values()->all() ?? [],
                ];
            })
            ->sortByDesc('cost_cents');

        return array_values($costs->all());
    }

    /**
     * Group the snapshot's resource and add-on usage items by category.
     *
     * @return list<array<string, mixed>>
     */
    protected function resourceBreakdown(UsageSnapshot $snapshot): array
    {
        $labels = [
            'database' => 'Databases',
            'cache' => 'Caches',
            'bucket' => 'Object storage',
            'websocket' => 'WebSockets',
            'addon' => 'Add-ons',
            'environment' => 'Environments',
        ];

        $groups = collect($labels)
            ->map(function (string $label, string $category) use ($snapshot): array {
                $items = $snapshot->items
                    ->where('category', $category)
                    ->sortByDesc('cost_cents')
                    ->map(fn (UsageItem $item): array => [
                        'name' => $item->name,
                        'cost_cents' => $item->cost_cents,
                        'type' => data_get($item->payload, 'type'),
                    ])
                    ->values();

                return [
                    'category' => $category,
                    'label' => $label,
                    'total_cents' => (int) $items->sum('cost_cents'),
                    'items' => $items->all(),
                ];
            })
            ->filter(fn (array $group): bool => $group['items'] !== []);

        return array_values($groups->all());
    }
}
