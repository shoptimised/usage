<?php

namespace App\Models;

use Database\Factories\UsageItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $usage_snapshot_id
 * @property string $category
 * @property string|null $name
 * @property int $cost_cents
 * @property float|null $burn_rate_cents_per_hour
 * @property array<array-key, mixed> $payload
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['usage_snapshot_id', 'category', 'name', 'cost_cents', 'burn_rate_cents_per_hour', 'payload'])]
class UsageItem extends Model
{
    /** @use HasFactory<UsageItemFactory> */
    use HasFactory;

    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'cost_cents' => 0,
    ];

    /**
     * Get the snapshot the usage item belongs to.
     *
     * @return BelongsTo<UsageSnapshot, $this>
     */
    public function usageSnapshot(): BelongsTo
    {
        return $this->belongsTo(UsageSnapshot::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cost_cents' => 'integer',
            'burn_rate_cents_per_hour' => 'float',
            'payload' => 'array',
        ];
    }
}
