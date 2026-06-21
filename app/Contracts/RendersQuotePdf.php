<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Models\Quote;

interface RendersQuotePdf
{
    /** Returns raw PDF bytes for the given quote. */
    public function render(Quote $quote): string;
}
