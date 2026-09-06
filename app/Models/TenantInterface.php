<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\HasUuids;
use App\Enums\TenantColorEnum;
use Database\Factories\TenantInterfaceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * @property string $id
 * @property string $tenant_id
 * @property TenantColorEnum|null $color
 */
#[Fillable(['tenant_id', 'color'])]
final class TenantInterface extends Model
{
    /** @use HasFactory<TenantInterfaceFactory> */
    use HasFactory, HasUuids, LogsActivity;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'color' => TenantColorEnum::class,
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['color'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
