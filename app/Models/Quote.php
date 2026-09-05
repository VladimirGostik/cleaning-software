<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasUuids;
use App\Enums\CurrencyEnum;
use App\Enums\QuoteKindEnum;
use App\Enums\QuoteStatusEnum;
use Database\Factories\QuoteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string|null $client_id
 * @property string|null $cleaning_object_id
 * @property QuoteStatusEnum $status
 * @property QuoteKindEnum $kind
 * @property string|null $number
 * @property string|null $subject
 * @property string|null $customer_name
 * @property string|null $customer_email
 * @property string|null $customer_street
 * @property string|null $customer_city
 * @property string|null $customer_postal_code
 * @property CurrencyEnum $currency
 * @property bool $is_vat_payer
 * @property Carbon $issue_date
 * @property Carbon $valid_until
 * @property Carbon|null $sent_at
 * @property Carbon|null $accepted_at
 * @property Carbon|null $rejected_at
 * @property string|null $vat_rate
 * @property string $subtotal
 * @property string $vat_amount
 * @property string $total
 * @property array<int, array<string, float>>|null $vat_breakdown
 * @property string|null $note
 * @property Client|null $client
 * @property CleaningObject|null $cleaningObject
 * @property Collection<int, QuoteItem> $items
 */
#[Fillable([
    'tenant_id',
    'client_id',
    'cleaning_object_id',
    'status',
    'kind',
    'number',
    'subject',
    'customer_name',
    'customer_email',
    'customer_street',
    'customer_city',
    'customer_postal_code',
    'issue_date',
    'valid_until',
    'sent_at',
    'accepted_at',
    'rejected_at',
    'is_vat_payer',
    'vat_rate',
    'currency',
    'subtotal',
    'vat_amount',
    'total',
    'vat_breakdown',
    'note',
])]
final class Quote extends Model implements HasMedia
{
    /** @use HasFactory<QuoteFactory> */
    use BelongsToTenant, HasFactory, HasUuids, InteractsWithMedia, LogsActivity, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => QuoteStatusEnum::class,
            'kind' => QuoteKindEnum::class,
            'currency' => CurrencyEnum::class,
            'issue_date' => 'date',
            'valid_until' => 'date',
            'sent_at' => 'datetime',
            'accepted_at' => 'datetime',
            'rejected_at' => 'datetime',
            'is_vat_payer' => 'boolean',
            'vat_rate' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'vat_amount' => 'decimal:2',
            'total' => 'decimal:2',
            'vat_breakdown' => 'array',
        ];
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('document')
            ->singleFile()
            ->useDisk(config('documents.disk'))
            ->acceptsMimeTypes(config('documents.allowed_mimes'));
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'kind', 'number', 'total', 'client_id'])
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
        return $this->hasMany(QuoteItem::class)->orderBy('position');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'quote_id');
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class, 'quote_id');
    }

    public function isEditable(): bool
    {
        return $this->status === QuoteStatusEnum::Draft;
    }

    public function canBeConverted(): bool
    {
        return $this->status === QuoteStatusEnum::Accepted;
    }

    public function isDocument(): bool
    {
        return $this->kind === QuoteKindEnum::Document;
    }

    public function scopeSearch(Builder $query, string $term): Builder
    {
        $operator = DB::getDriverName() === 'pgsql' ? 'ilike' : 'like';

        return $query->where(function (Builder $q) use ($term, $operator): void {
            $q->where('number', $operator, '%' . $term . '%')
                ->orWhere('subject', $operator, '%' . $term . '%')
                ->orWhere('customer_name', $operator, '%' . $term . '%');
        });
    }
}
