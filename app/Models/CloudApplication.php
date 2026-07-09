<?php

namespace App\Models;

use Database\Factories\CloudApplicationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $cloud_id
 * @property string $name
 * @property string $slug
 * @property string $region
 * @property string|null $repository
 * @property string|null $avatar_url
 * @property Carbon|null $cloud_created_at
 * @property Carbon|null $last_synced_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['cloud_id', 'name', 'slug', 'region', 'repository', 'avatar_url', 'cloud_created_at', 'last_synced_at'])]
class CloudApplication extends Model
{
    /** @use HasFactory<CloudApplicationFactory> */
    use HasFactory;

    /**
     * Get the environments belonging to the application.
     *
     * @return HasMany<CloudEnvironment, $this>
     */
    public function environments(): HasMany
    {
        return $this->hasMany(CloudEnvironment::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cloud_created_at' => 'datetime',
            'last_synced_at' => 'datetime',
        ];
    }
}
