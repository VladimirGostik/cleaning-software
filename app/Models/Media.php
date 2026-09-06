<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use RuntimeException;
use Spatie\MediaLibrary\MediaCollections\Models\Media as SpatieMedia;

/**
 * @property string $tenant_id
 */
final class Media extends SpatieMedia
{
    protected static function booted(): void
    {
        self::creating(function (self $media): void {
            if ($media->getAttribute('tenant_id') !== null) {
                return;
            }

            if (! app()->bound('current_tenant_id')) {
                throw new RuntimeException('media.tenant_context_missing');
            }

            $media->setAttribute('tenant_id', current_tenant_id());
        });
    }

    /**
     * @param  Builder<Media>  $query
     * @return Builder<Media>
     */
    public function scopeInTenant(Builder $query, string $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }
}
