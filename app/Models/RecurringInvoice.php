<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasUuids;
use App\Enums\InvoiceTemplateEnum;
use App\Enums\InvoiceTypeEnum;
use App\Enums\RecurringFrequencyEnum;
use App\Enums\RecurringInvoiceStatusEnum;
use Database\Factories\RecurringInvoiceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * @property RecurringInvoiceStatusEnum $status
 * @property RecurringFrequencyEnum $frequency
 * @property InvoiceTypeEnum $type
 * @property InvoiceTemplateEnum|null $template
 * @property bool $auto_issue
 * @property int $day_of_month
 * @property Carbon $start_date
 * @property Carbon|null $end_date
 * @property Carbon|null $next_run_at
 * @property Carbon|null $last_generated_at
 * @property Carbon|null $period_from
 * @property Carbon|null $period_to
 * @property int|null $occurrences_limit
 * @property int $occurrences_generated
 * @property int $due_days
 */
#[Fillable([
    'tenant_id',
    'client_id',
    'cleaning_object_id',
    'name',
    'type',
    'template',
    'frequency',
    'day_of_month',
    'status',
    'auto_issue',
    'start_date',
    'end_date',
    'occurrences_limit',
    'occurrences_generated',
    'next_run_at',
    'last_generated_at',
    'customer_name',
    'customer_representative',
    'customer_ico',
    'customer_dic',
    'customer_vat_number',
    'customer_street',
    'customer_city',
    'customer_postal_code',
    'customer_country',
    'customer_email',
    'period_from',
    'period_to',
    'due_days',
    'note',
])]
final class RecurringInvoice extends Model
{
    /** @use HasFactory<RecurringInvoiceFactory> */
    use BelongsToTenant, HasFactory, HasUuids, LogsActivity, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => InvoiceTypeEnum::class,
            'template' => InvoiceTemplateEnum::class,
            'frequency' => RecurringFrequencyEnum::class,
            'status' => RecurringInvoiceStatusEnum::class,
            'auto_issue' => 'boolean',
            'day_of_month' => 'integer',
            'start_date' => 'date',
            'end_date' => 'date',
            'next_run_at' => 'date',
            'period_from' => 'date',
            'period_to' => 'date',
            'last_generated_at' => 'datetime',
            'occurrences_limit' => 'integer',
            'occurrences_generated' => 'integer',
            'due_days' => 'integer',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'frequency', 'next_run_at', 'occurrences_generated'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function cleaningObject(): BelongsTo
    {
        return $this->belongsTo(CleaningObject::class, 'cleaning_object_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(RecurringInvoiceItem::class)->orderBy('position');
    }

    public function generatedInvoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function isRunnable(): bool
    {
        return $this->status->isRunnable() && $this->next_run_at !== null;
    }

    public function hasReachedLimit(): bool
    {
        return $this->occurrences_limit !== null && $this->occurrences_generated >= $this->occurrences_limit;
    }

    public function hasReachedEndDate(Carbon $on): bool
    {
        return $this->end_date !== null && $on->gt($this->end_date);
    }

    public function scopeSearch(Builder $query, string $term): Builder
    {
        $driver = DB::getDriverName();
        $operator = $driver === 'pgsql' ? 'ilike' : 'like';

        return $query->where(function (Builder $q) use ($term, $operator): void {
            $q->where('name', $operator, '%' . $term . '%')
                ->orWhere('customer_name', $operator, '%' . $term . '%');
        });
    }
}
