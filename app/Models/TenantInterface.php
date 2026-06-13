<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\InvoiceTemplateEnum;
use App\Enums\TenantColorEnum;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property InvoiceTemplateEnum|null $invoice_template
 */
#[Fillable(['tenant_id', 'color', 'invoice_template'])]
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
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
