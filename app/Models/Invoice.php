<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasUuids;
use App\Enums\CurrencyEnum;
use App\Enums\InvoiceStatusEnum;
use App\Enums\InvoiceTemplateEnum;
use App\Enums\InvoiceTypeEnum;
use App\Enums\PaymentTypeEnum;
use App\Enums\RoundingModeEnum;
use Database\Factories\InvoiceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string|null $client_id
 * @property string|null $cleaning_object_id
 * @property string|null $credited_invoice_id
 * @property string|null $recurring_invoice_id
 * @property InvoiceStatusEnum $status
 * @property InvoiceTypeEnum $type
 * @property InvoiceTemplateEnum $template
 * @property PaymentTypeEnum $payment_type
 * @property CurrencyEnum $currency
 * @property RoundingModeEnum $rounding_mode
 * @property bool $is_vat_payer
 * @property string|null $number
 * @property Carbon $issue_date
 * @property Carbon $delivery_date
 * @property Carbon $due_date
 * @property Carbon|null $issued_at
 * @property Carbon|null $sent_at
 * @property Carbon|null $paid_at
 * @property Carbon|null $cancelled_at
 * @property Carbon|null $period_from
 * @property Carbon|null $period_to
 * @property string|null $vat_rate
 * @property string $subtotal
 * @property string $vat_amount
 * @property string $total
 * @property string $deposit
 * @property string $rounding_amount
 * @property float $balance_due
 * @property array<int, array<string, float>>|null $vat_breakdown
 * @property string $customer_name
 * @property string|null $customer_email
 * @property Collection<int, InvoiceItem> $items
 * @property Client|null $client
 * @property CleaningObject|null $cleaningObject
 */
#[Fillable([
    'tenant_id',
    'client_id',
    'cleaning_object_id',
    'credited_invoice_id',
    'recurring_invoice_id',
    'type',
    'status',
    'template',
    'number',
    'variable_symbol',
    'period_from',
    'period_to',
    'issue_date',
    'delivery_date',
    'due_date',
    'issued_at',
    'sent_at',
    'paid_at',
    'cancelled_at',
    'is_vat_payer',
    'vat_rate',
    'subtotal',
    'vat_amount',
    'total',
    'deposit',
    'vat_breakdown',
    'rounding_amount',
    'constant_symbol',
    'specific_symbol',
    'payment_type',
    'currency',
    'rounding_mode',
    'header_text',
    'footer_text',
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
    'object_name',
    'object_street',
    'object_city',
    'object_postal_code',
    'supplier_name',
    'supplier_ico',
    'supplier_dic',
    'supplier_vat_number',
    'supplier_iban',
    'supplier_swift',
    'supplier_address_line',
    'supplier_city',
    'supplier_postal_code',
    'supplier_country',
    'supplier_contact_email',
    'supplier_contact_phone',
    'supplier_registration_info',
    'note',
])]
final class Invoice extends Model
{
    /** @use HasFactory<InvoiceFactory> */
    use BelongsToTenant, HasFactory, HasUuids, LogsActivity, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => InvoiceTypeEnum::class,
            'status' => InvoiceStatusEnum::class,
            'template' => InvoiceTemplateEnum::class,
            'payment_type' => PaymentTypeEnum::class,
            'currency' => CurrencyEnum::class,
            'rounding_mode' => RoundingModeEnum::class,
            'period_from' => 'date',
            'period_to' => 'date',
            'issue_date' => 'date',
            'delivery_date' => 'date',
            'due_date' => 'date',
            'issued_at' => 'datetime',
            'sent_at' => 'datetime',
            'paid_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'is_vat_payer' => 'boolean',
            'vat_rate' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'vat_amount' => 'decimal:2',
            'total' => 'decimal:2',
            'deposit' => 'decimal:2',
            'rounding_amount' => 'decimal:2',
            'vat_breakdown' => 'array',
        ];
    }

    /** @return Attribute<float, never> */
    protected function balanceDue(): Attribute
    {
        return Attribute::get(fn (): float => (float) $this->total - (float) $this->deposit);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'number', 'total', 'client_id', 'issued_at', 'paid_at', 'cancelled_at', 'sent_at'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    /**
     * `withTrashed()` — a soft-deleted client must still resolve `client_name` on old invoices.
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
     * @return HasMany<InvoiceItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class)->orderBy('position');
    }

    /**
     * @return BelongsTo<Invoice, $this>
     */
    public function creditedInvoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'credited_invoice_id');
    }

    /**
     * @return HasOne<Invoice, $this>
     */
    public function creditNote(): HasOne
    {
        return $this->hasOne(Invoice::class, 'credited_invoice_id');
    }

    /**
     * @return BelongsTo<RecurringInvoice, $this>
     */
    public function recurringInvoice(): BelongsTo
    {
        return $this->belongsTo(RecurringInvoice::class);
    }

    public function isEditable(): bool
    {
        return $this->status === InvoiceStatusEnum::Draft;
    }

    public function canBeCancelled(): bool
    {
        return in_array($this->status, [InvoiceStatusEnum::Issued, InvoiceStatusEnum::Overdue], true);
    }

    /**
     * Safe base filename (no extension) for PDF/attachment downloads. Strips characters
     * that would break a `Content-Disposition` header (quotes, control chars, path
     * separators, `%`) out of the user-influenced invoice number.
     */
    public function pdfFilenameBase(): string
    {
        $number = $this->number ?? 'draft';
        $safe = preg_replace('/[\x00-\x1F\x7F"\/\\%]/', '', $number);

        return $safe !== null && $safe !== '' ? $safe : 'draft';
    }
}
