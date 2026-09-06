<?php

declare(strict_types=1);

namespace App\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum InvoiceTemplateEnum: string
{
    case Classic = 'classic';
    case Modern = 'modern';
    case Minimal = 'minimal';

    public function view(): string
    {
        return 'pdf.invoices.'.$this->value;
    }

    public function label(): string
    {
        return __('app.invoice_template_'.$this->value);
    }
}
