<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasUuids;
use App\Enums\PaymentSourceEnum;
use App\Enums\PaymentTypeEnum;
use Database\Factories\InvoicePaymentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $invoice_id
 * @property string $amount
 * @property Carbon $paid_on
 * @property PaymentTypeEnum $payment_type
 * @property PaymentSourceEnum $source
 * @property string|null $external_reference
 * @property string|null $note
 * @property string|null $recorded_by_user_id
 * @property Carbon $created_at
 * @property User|null $recordedBy
 */
#[Fillable([
    'tenant_id',
    'invoice_id',
    'amount',
    'paid_on',
    'payment_type',
    'source',
    'external_reference',
    'note',
    'recorded_by_user_id',
])]
final class InvoicePayment extends Model
{
    /** @use HasFactory<InvoicePaymentFactory> */
    use BelongsToTenant, HasFactory, HasUuids, LogsActivity, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_on' => 'date',
            'payment_type' => PaymentTypeEnum::class,
            'source' => PaymentSourceEnum::class,
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['amount', 'paid_on', 'payment_type', 'source', 'external_reference'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    /**
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id');
    }
}
