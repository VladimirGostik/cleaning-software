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

    /** Pinned Chrome page footer view, when this template has one. */
    public function footerView(): ?string
    {
        return match ($this) {
            self::Classic => 'pdf.invoices.classic-footer',
            default => null,
        };
    }
}
