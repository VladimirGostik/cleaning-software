<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\HasUuids;
use App\Enums\CurrencyEnum;
use App\Enums\PaymentTypeEnum;
use App\Enums\RecurringDefaultStateEnum;
use App\Enums\RoundingModeEnum;
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
 * @property RecurringDefaultStateEnum $recurring_default_state
 * @property string|null $default_constant_symbol
 * @property PaymentTypeEnum $default_payment_type
 * @property CurrencyEnum $default_currency
 * @property RoundingModeEnum $default_rounding_mode
 * @property string|null $default_header_text
 * @property string|null $default_footer_text
 * @property string|null $invoice_mail_subject
 * @property string|null $invoice_mail_body
 * @property array<string, mixed>|null $bank_csv_mapping
 */
#[Fillable([
    'tenant_id',
    'color',
    'recurring_default_state',
    'default_constant_symbol',
    'default_payment_type',
    'default_currency',
    'default_rounding_mode',
    'default_header_text',
    'default_footer_text',
    'invoice_mail_subject',
    'invoice_mail_body',
    'bank_csv_mapping',
])]
final class TenantInterface extends Model
{
    /** @use HasFactory<TenantInterfaceFactory> */
    use HasFactory, HasUuids, LogsActivity;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'color' => TenantColorEnum::class,
            'recurring_default_state' => RecurringDefaultStateEnum::class,
            'default_payment_type' => PaymentTypeEnum::class,
            'default_currency' => CurrencyEnum::class,
            'default_rounding_mode' => RoundingModeEnum::class,
            'bank_csv_mapping' => 'array',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'color', 'recurring_default_state',
                'default_constant_symbol', 'default_payment_type', 'default_currency', 'default_rounding_mode',
                'default_header_text', 'default_footer_text', 'invoice_mail_subject', 'invoice_mail_body',
            ])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
