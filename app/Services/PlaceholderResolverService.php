<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\Formats;
use App\Data\Contracts\PlaceholderTokenData;
use App\Data\Invoices\InvoiceMailPlaceholderCatalogData;
use App\Models\Invoice;

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
            'invoice.total' => $currency->format((float) $invoice->total),
            'invoice.balance_due' => $currency->format($invoice->balance_due),
            'invoice.issue_date' => $invoice->issue_date->format(Formats::DATE),
            'invoice.due_date' => $invoice->due_date->format(Formats::DATE),
            'invoice.period' => $period,
            'customer.name' => $invoice->customer_name,
            'supplier.name' => $invoice->supplier_name,
            'supplier.iban' => $invoice->supplier_iban,
            'supplier.contact_email' => $invoice->supplier_contact_email,
            'supplier.contact_phone' => $invoice->supplier_contact_phone,
        ];
    }

    public function invoiceMailCatalog(): InvoiceMailPlaceholderCatalogData
    {
        $tokens = [
            'invoice.number', 'invoice.variable_symbol', 'invoice.total', 'invoice.balance_due',
            'invoice.issue_date', 'invoice.due_date', 'invoice.period',
            'customer.name', 'supplier.name', 'supplier.iban', 'supplier.contact_email', 'supplier.contact_phone',
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
