<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasPdfFilename;
use App\Concerns\HasUuids;
use App\Enums\ContractCategoryEnum;
use App\Enums\ContractStatusEnum;
use App\Enums\ContractTermTypeEnum;
use Database\Factories\ContractFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string|null $contract_template_id
 * @property string|null $quote_id
 * @property string $contractable_type
 * @property string $contractable_id
 * @property ContractCategoryEnum $category
 * @property ContractStatusEnum $status
 * @property ContractTermTypeEnum $term_type
 * @property string $title
 * @property string|null $number
 * @property string $body
 * @property Carbon $valid_from
 * @property Carbon|null $end_date
 * @property Carbon|null $signed_at
 * @property Carbon|null $terminated_at
 * @property string|null $termination_reason
 * @property string|null $notes
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 * @property ContractTemplate|null $contractTemplate
 * @property Quote|null $quote
 * @property CleaningObject|TenantMembership|Model $contractable
 * @property EmploymentContract|null $employmentContract
 */
#[Fillable([
    'tenant_id', 'contract_template_id', 'quote_id', 'contractable_type', 'contractable_id',
    'category', 'status', 'term_type', 'title', 'number', 'body',
    'valid_from', 'end_date', 'signed_at', 'terminated_at', 'termination_reason', 'notes',
])]
final class Contract extends Model
{
    /** @use HasFactory<ContractFactory> */
    use BelongsToTenant, HasFactory, HasPdfFilename, HasUuids, LogsActivity, SoftDeletes;

    /** @return array<string, string> */
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
            ->logOnly(['status', 'signed_at', 'terminated_at', 'number', 'title', 'contractable_id'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    /**
     * `withTrashed()` — a soft-deleted template must still resolve its name on existing contracts.
     *
     * @return BelongsTo<ContractTemplate, $this>
     */
    public function contractTemplate(): BelongsTo
    {
        return $this->belongsTo(ContractTemplate::class)->withTrashed();
    }

    /** @return BelongsTo<Quote, $this> */
    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class)->withTrashed();
    }

    /** @return MorphTo<Model, $this> */
    public function contractable(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return HasOne<EmploymentContract, $this> */
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

    public function contractableLabel(): string
    {
        $contractable = $this->contractable;

        if ($contractable instanceof CleaningObject) {
            return $contractable->name;
        }

        if ($contractable instanceof TenantMembership) {
            $name = trim(($contractable->first_name ?? '').' '.($contractable->last_name ?? ''));

            if ($name === '') {
                $name = $contractable->user->name ?? '';
            }

            return trim($name.' ('.($contractable->user->email ?? '').')');
        }

        return '';
    }
}
