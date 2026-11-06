<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\Contracts\PlaceholderTokenData;
use App\Data\Invoices\InvoiceMailPlaceholderCatalogData;
use App\Models\Invoice;
use App\Models\TenantInterface;
use App\Support\Formats;

/** Plain-text `{{token}}` substitution for invoice mail; documents use `DocumentTemplateRenderer`. */
final readonly class PlaceholderResolverService
{
    /**
     * @param  array<string, string|null>  $variables
     */
    public function resolve(string $body, array $variables): string
    {
        foreach ($variables as $key => $value) {
            $body = str_replace('{{'.$key.'}}', $value ?? '', $body);
        }

        return $body;
    }

    /**
     * @return array<string, string|null>
     */
    public function invoiceMailVariables(Invoice $invoice): array
    {
        $currency = $invoice->currency;
        $period = $invoice->period_from !== null && $invoice->period_to !== null
            ? $invoice->period_from->format(Formats::DATE).' – '.$invoice->period_to->format(Formats::DATE)
            : null;

        return [
            'invoice.number' => $invoice->number,
            'invoice.variable_symbol' => $invoice->variable_symbol,
            'invoice.subtotal' => $currency->format((float) $invoice->subtotal),
            'invoice.vat_amount' => $currency->format((float) $invoice->vat_amount),
            'invoice.total' => $currency->format((float) $invoice->total),
            'invoice.balance_due' => $currency->format($invoice->balance_due),
            'invoice.issue_date' => $invoice->issue_date->format(Formats::DATE),
            'invoice.due_date' => $invoice->due_date->format(Formats::DATE),
            'invoice.period' => $period,
            'object.name' => $invoice->object_name,
            'customer.name' => $invoice->customer_name,
            'supplier.name' => $invoice->supplier_name,
            'supplier.iban' => $invoice->supplier_iban,
            'supplier.contact_email' => $invoice->supplier_contact_email,
            'supplier.contact_phone' => $invoice->supplier_contact_phone,
        ];
    }

    /**
     * @return array{subject: string, body: string}
     */
    public function invoiceMailContent(Invoice $invoice, ?string $subject = null, ?string $body = null): array
    {
        $interface = TenantInterface::query()->where('tenant_id', $invoice->tenant_id)->first();
        $variables = $this->invoiceMailVariables($invoice);

        $subjectTemplate = collect([$subject, $interface?->invoice_mail_subject])->first(fn (?string $value): bool => filled($value));
        $bodyTemplate = collect([$body, $interface?->invoice_mail_body])->first(fn (?string $value): bool => filled($value));

        return [
            'subject' => $subjectTemplate !== null
                ? $this->resolve($subjectTemplate, $variables)
                : __('app.invoice_mail_subject', ['number' => $invoice->number ?? __('app.invoice_pdf_draft')]),
            'body' => $bodyTemplate !== null
                ? $this->resolve($bodyTemplate, $variables)
                : __('app.invoice_mail_greeting', ['name' => $invoice->customer_name])."\n\n".__('app.invoice_mail_body'),
        ];
    }

    public function invoiceMailCatalog(): InvoiceMailPlaceholderCatalogData
    {
        $tokens = [
            'invoice.number', 'invoice.variable_symbol', 'invoice.subtotal', 'invoice.vat_amount', 'invoice.total', 'invoice.balance_due',
            'invoice.issue_date', 'invoice.due_date', 'invoice.period',
            'object.name', 'customer.name', 'supplier.name', 'supplier.iban', 'supplier.contact_email', 'supplier.contact_phone',
        ];

        return new InvoiceMailPlaceholderCatalogData(
            tokens: array_map(
                fn (string $token): PlaceholderTokenData => new PlaceholderTokenData(
                    token: '{{'.$token.'}}',
                    label: __('app.invoice_mail_token_'.str_replace('.', '_', $token)),
                ),
                $tokens,
            ),
        );
    }
}
