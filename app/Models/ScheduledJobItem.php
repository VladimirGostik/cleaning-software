<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasUuids;
use Database\Factories\ScheduledJobItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $cleaning_job_id
 * @property string|null $object_visit_item_id
 * @property string $name
 * @property string $unit_price
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property ScheduledJob|null $job
 */
#[Table('cleaning_job_items')]
#[Fillable(['tenant_id', 'cleaning_job_id', 'object_visit_item_id', 'name', 'unit_price'])]
final class ScheduledJobItem extends Model
{
    /** @use HasFactory<ScheduledJobItemFactory> */
    use BelongsToTenant, HasFactory, HasUuids;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['unit_price' => 'decimal:2'];
    }

    /** @return BelongsTo<ScheduledJob, $this> */
    public function job(): BelongsTo
    {
        return $this->belongsTo(ScheduledJob::class, 'cleaning_job_id');
    }
}
