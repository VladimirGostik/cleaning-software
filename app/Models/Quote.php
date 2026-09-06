<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasPdfFilename;
use App\Concerns\HasUuids;
use App\Enums\CurrencyEnum;
use App\Enums\QuoteKindEnum;
use App\Enums\QuoteStatusEnum;
use Database\Factories\QuoteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
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
 * @property Carbon $issue_date
 * @property Carbon $valid_until
 * @property Carbon|null $sent_at
 * @property Carbon|null $accepted_at
 * @property Carbon|null $rejected_at
 * @property bool $is_vat_payer
 * @property string|null $vat_rate
 * @property CurrencyEnum $currency
 * @property string $subtotal
 * @property string $vat_amount
 * @property string $total
 * @property array<int, array<string, float>>|null $vat_breakdown
 * @property string|null $note
 * @property Collection<int, QuoteItem> $items
 * @property Collection<int, Invoice> $invoices
 * @property Collection<int, Contract> $contracts
 * @property Client|null $client
 * @property CleaningObject|null $cleaningObject
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
    use BelongsToTenant, HasFactory, HasPdfFilename, HasUuids, InteractsWithMedia, LogsActivity, SoftDeletes;

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
        $disk = config('quotes.document.disk', 'local');

        $this->addMediaCollection('document')
            ->singleFile()
            ->useDisk(is_string($disk) ? $disk : 'local');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'kind', 'number', 'total', 'client_id', 'cleaning_object_id'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    /**
     * `withTrashed()` — a soft-deleted client must still resolve `client_name` on old quotes.
     *
     * @return BelongsTo<Client, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class)->withTrashed();
    }

    /**
     * @return BelongsTo<CleaningObject, $this>
     */
    public function cleaningObject(): BelongsTo
    {
        return $this->belongsTo(CleaningObject::class, 'cleaning_object_id')->withTrashed();
    }

    /**
     * @return HasMany<QuoteItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(QuoteItem::class)->orderBy('position');
    }

    /**
     * @return HasMany<Invoice, $this>
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * @return HasMany<Contract, $this>
     */
    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class);
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
}
