<?php

namespace App\Models;

use Database\Factories\UsageSnapshotFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $period
 * @property Carbon|null $period_from
 * @property Carbon|null $period_to
 * @property string|null $currency
 * @property int $current_spend_cents
 * @property int|null $bandwidth_cost_cents
 * @property int|null $bandwidth_usage_percentage
 * @property int|null $bandwidth_allowance_bytes
 * @property int|null $credits_used_cents
 * @property int|null $credits_total_cents
 * @property int|null $alert_threshold_cents
 * @property int|null $alert_remaining_percentage
 * @property int $resources_cost_cents
 * @property int $addons_cost_cents
 * @property int $applications_cost_cents
 * @property int $application_count
 * @property float|null $burn_rate_cents_per_hour
 * @property Carbon|null $cloud_updated_at
 * @property array<array-key, mixed> $raw
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'period',
    'period_from',
    'period_to',
    'currency',
    'current_spend_cents',
    'bandwidth_cost_cents',
    'bandwidth_usage_percentage',
    'bandwidth_allowance_bytes',
    'credits_used_cents',
    'credits_total_cents',
    'alert_threshold_cents',
    'alert_remaining_percentage',
    'resources_cost_cents',
    'addons_cost_cents',
    'applications_cost_cents',
    'application_count',
    'burn_rate_cents_per_hour',
    'cloud_updated_at',
    'raw',
])]
class UsageSnapshot extends Model
{
    /** @use HasFactory<UsageSnapshotFactory> */
    use HasFactory;

    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'current_spend_cents' => 0,
        'resources_cost_cents' => 0,
        'addons_cost_cents' => 0,
        'applications_cost_cents' => 0,
        'application_count' => 0,
    ];

    /**
     * Get the usage line items belonging to the snapshot.
     *
     * @return HasMany<UsageItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(UsageItem::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'period' => 'integer',
            'period_from' => 'datetime',
            'period_to' => 'datetime',
            'current_spend_cents' => 'integer',
            'bandwidth_cost_cents' => 'integer',
            'bandwidth_usage_percentage' => 'integer',
            'bandwidth_allowance_bytes' => 'integer',
            'credits_used_cents' => 'integer',
            'credits_total_cents' => 'integer',
            'alert_threshold_cents' => 'integer',
            'alert_remaining_percentage' => 'integer',
            'resources_cost_cents' => 'integer',
            'addons_cost_cents' => 'integer',
            'applications_cost_cents' => 'integer',
            'application_count' => 'integer',
            'burn_rate_cents_per_hour' => 'float',
            'cloud_updated_at' => 'datetime',
            'raw' => 'array',
        ];
    }
}
