<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasUuids;
use App\Enums\TaskFrequencyEnum;
use Database\Factories\QuoteItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $quote_id
 * @property string $description
 * @property TaskFrequencyEnum|null $frequency
 * @property string|null $note
 * @property string $quantity
 * @property string|null $unit
 * @property string $unit_price
 * @property string $discount_percent
 * @property string $vat_rate
 * @property string $line_base
 * @property string $line_vat
 * @property string $line_total
 * @property int $position
 */
#[Fillable([
    'tenant_id',
    'quote_id',
    'description',
    'frequency',
    'note',
    'quantity',
    'unit',
    'unit_price',
    'discount_percent',
    'vat_rate',
    'line_base',
    'line_vat',
    'line_total',
    'position',
])]
final class QuoteItem extends Model
{
    /** @use HasFactory<QuoteItemFactory> */
    use BelongsToTenant, HasFactory, HasUuids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'frequency' => TaskFrequencyEnum::class,
            'quantity' => 'decimal:2',
            'unit_price' => 'decimal:2',
            'discount_percent' => 'decimal:2',
            'vat_rate' => 'decimal:2',
            'line_base' => 'decimal:2',
            'line_vat' => 'decimal:2',
            'line_total' => 'decimal:2',
            'position' => 'integer',
        ];
    }

    /** @return BelongsTo<Quote, $this> */
    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }
}
