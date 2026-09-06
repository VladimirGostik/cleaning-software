<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Models\Quote;

interface RendersQuotePdf
{
    public function render(Quote $quote): string;
}
