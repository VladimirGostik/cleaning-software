<?php

declare(strict_types=1);

namespace App\Concerns;

trait IsDocumentItem
{
    public function initializeIsDocumentItem(): void
    {
        $this->mergeCasts([
            'quantity' => 'decimal:2',
            'unit_price' => 'decimal:2',
            'discount_percent' => 'decimal:2',
            'vat_rate' => 'decimal:2',
            'position' => 'integer',
        ]);
    }
}
