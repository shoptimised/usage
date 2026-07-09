<?php

namespace App\Models;

use Database\Factories\CloudEnvironmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $cloud_application_id
 * @property string $cloud_id
 * @property string $name
 * @property string $slug
 * @property string $status
 * @property string|null $vanity_domain
 * @property string|null $php_major_version
 * @property bool $uses_octane
 * @property bool $uses_hibernation
 * @property Carbon|null $cloud_created_at
 * @property Carbon|null $last_synced_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['cloud_application_id', 'cloud_id', 'name', 'slug', 'status', 'vanity_domain', 'php_major_version', 'uses_octane', 'uses_hibernation', 'cloud_created_at', 'last_synced_at'])]
class CloudEnvironment extends Model
{
    /** @use HasFactory<CloudEnvironmentFactory> */
    use HasFactory;

    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'uses_octane' => false,
        'uses_hibernation' => false,
    ];

    /**
     * Get the application the environment belongs to.
     *
     * @return BelongsTo<CloudApplication, $this>
     */
    public function cloudApplication(): BelongsTo
    {
        return $this->belongsTo(CloudApplication::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'uses_octane' => 'boolean',
            'uses_hibernation' => 'boolean',
            'cloud_created_at' => 'datetime',
            'last_synced_at' => 'datetime',
        ];
    }
}
