<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasUuids;
use Database\Factories\RecurringInvoiceItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $recurring_invoice_id
 * @property string $description
 * @property string $quantity
 * @property string|null $unit
 * @property string $unit_price
 * @property string $discount_percent
 * @property string $vat_rate
 * @property int $position
 */
#[Fillable([
    'tenant_id',
    'recurring_invoice_id',
    'description',
    'quantity',
    'unit',
    'unit_price',
    'discount_percent',
    'vat_rate',
    'position',
])]
final class RecurringInvoiceItem extends Model
{
    /** @use HasFactory<RecurringInvoiceItemFactory> */
    use BelongsToTenant, HasFactory, HasUuids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'unit_price' => 'decimal:2',
            'discount_percent' => 'decimal:2',
            'vat_rate' => 'decimal:2',
            'position' => 'integer',
        ];
    }

    /** @return BelongsTo<RecurringInvoice, $this> */
    public function recurringInvoice(): BelongsTo
    {
        return $this->belongsTo(RecurringInvoice::class);
    }
}
