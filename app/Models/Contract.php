<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasUuids;
use App\Enums\ContractCategoryEnum;
use App\Enums\ContractStatusEnum;
use App\Enums\ContractTermTypeEnum;
use Database\Factories\ContractFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * @property ContractStatusEnum $status
 * @property ContractCategoryEnum $category
 * @property ContractTermTypeEnum $term_type
 * @property Carbon $valid_from
 * @property Carbon|null $end_date
 * @property Carbon|null $signed_at
 * @property Carbon|null $terminated_at
 * @property CleaningObject|TenantMembership|null $contractable
 * @property ContractTemplate|null $contractTemplate
 * @property EmploymentContract|null $employmentContract
 */
#[Fillable([
    'tenant_id',
    'contract_template_id',
    'contractable_type',
    'contractable_id',
    'category',
    'status',
    'term_type',
    'title',
    'reference_number',
    'body',
    'valid_from',
    'end_date',
    'signed_at',
    'terminated_at',
    'termination_reason',
    'notes',
])]
final class Contract extends Model
{
    /** @use HasFactory<ContractFactory> */
    use BelongsToTenant, HasFactory, HasUuids, LogsActivity, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => ContractCategoryEnum::class,
            'status' => ContractStatusEnum::class,
            'term_type' => ContractTermTypeEnum::class,
            'valid_from' => 'date',
            'end_date' => 'date',
            'signed_at' => 'datetime',
            'terminated_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'signed_at', 'terminated_at', 'reference_number', 'title'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    public function contractTemplate(): BelongsTo
    {
        return $this->belongsTo(ContractTemplate::class);
    }

    public function contractable(): MorphTo
    {
        return $this->morphTo();
    }

    public function employmentContract(): HasOne
    {
        return $this->hasOne(EmploymentContract::class);
    }

    public function isEditable(): bool
    {
        return $this->status->isEditable();
    }

    public function canBeSigned(): bool
    {
        return $this->status->canBeSigned();
    }

    public function canBeTerminated(): bool
    {
        return $this->status->canBeTerminated();
    }

    public function scopeSearch(Builder $query, string $term): Builder
    {
        $operator = DB::getDriverName() === 'pgsql' ? 'ilike' : 'like';

        return $query->where(function (Builder $q) use ($term, $operator): void {
            $q->where('title', $operator, '%' . $term . '%')
                ->orWhere('reference_number', $operator, '%' . $term . '%');
        });
    }
}
