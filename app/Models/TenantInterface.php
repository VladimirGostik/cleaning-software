<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CurrencyEnum;
use App\Enums\InvoiceTemplateEnum;
use App\Enums\PaymentTypeEnum;
use App\Enums\RecurringDefaultStateEnum;
use App\Enums\RoundingModeEnum;
use App\Enums\TenantColorEnum;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property InvoiceTemplateEnum|null $invoice_template
 * @property RecurringDefaultStateEnum $recurring_default_state
 * @property PaymentTypeEnum $default_payment_type
 * @property CurrencyEnum $default_currency
 * @property RoundingModeEnum $default_rounding_mode
 */
#[Fillable([
    'tenant_id',
    'color',
    'invoice_template',
    'recurring_default_state',
    'default_constant_symbol',
    'default_payment_type',
    'default_currency',
    'default_rounding_mode',
])]
final class TenantInterface extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'color' => TenantColorEnum::class,
            'invoice_template' => InvoiceTemplateEnum::class,
            'recurring_default_state' => RecurringDefaultStateEnum::class,
            'default_payment_type' => PaymentTypeEnum::class,
            'default_currency' => CurrencyEnum::class,
            'default_rounding_mode' => RoundingModeEnum::class,
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
