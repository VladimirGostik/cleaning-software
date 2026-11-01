@php
    $hasDiscount = $invoice->items->contains(fn ($item) => (float) $item->discount_percent > 0);
    $currencyEnum = $invoice->currency ?? \App\Enums\CurrencyEnum::EUR;
    $amountDue = $invoice->amount_payable;
    $documentType = $invoice->isCreditNote() ? __('app.invoice_credit_note') : __('app.invoice_pdf_title');
    $showStatusBanner = in_array($invoice->status, [
        \App\Enums\InvoiceStatusEnum::Draft,
        \App\Enums\InvoiceStatusEnum::Cancelled,
    ], true);
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>{{ $documentType }} {{ $invoice->number ?? __('app.invoice_pdf_draft') }}</title>
    @if($webFonts ?? false)
    @include('pdf.partials.kegos-webfont')
    @endif
    @include('pdf.partials.kegos-base')
    <style>
        .parties td { vertical-align: top; padding: 0; width: 50%; }
        .parties td + td { padding-left: 8mm; }
        .meta-lines { font-size: 9.5pt; }
        .meta td { padding: 1px 0; font-size: 9.5pt; }
        .meta td:first-child { color: #5A6661; padding-right: 8px; white-space: nowrap; }
        .block { margin-top: 5mm; }
        .sums td { padding: 3px 0; }
        .sums .grand td { padding-top: 6px; border-top: 1px solid #111C18; font-size: 13pt; }
        .sig-frame { display: inline-block; width: 150px; height: 90px; border: 1px solid #E9ECEA; border-radius: 8px; text-align: center; }
        .pay { page-break-inside: avoid; }
        .pay td { vertical-align: middle; }
        .pay-cell { padding: 10px 14px; }
        .pay-cell .l { font-size: 8pt; color: #5A6661; }
        .pay-cell .v { font-size: 11pt; font-weight: 600; font-variant-numeric: tabular-nums; }
    </style>
</head>
<body>

@include('pdf.partials.kegos-header', [
    'documentType' => $documentType,
    'number' => $invoice->number ?? __('app.invoice_pdf_draft'),
])

@if($invoice->isCreditNote())
<div class="muted" style="margin-bottom:5mm;">{{ __('app.invoice_credit_note_for') }} {{ $invoice->creditedInvoice?->number }}</div>
@endif

@if($showStatusBanner)
<div style="margin-bottom:5mm;"><span class="kg-chip">{{ __('app.invoice_pdf_status') }}: {{ $invoice->status->label() }}</span></div>
@endif

@if($invoice->header_text)
<div class="muted" style="margin-bottom:5mm;">{{ $invoice->header_text }}</div>
@endif

<table class="parties">
    <tr>
        <td>
            <div class="kg-label">{{ __('app.invoice_pdf_supplier') }}</div>
            <div class="kg-name">{{ $invoice->supplier_name }}</div>
            @if($invoice->supplier_address_line){{ $invoice->supplier_address_line }}<br>@endif
            @if($invoice->supplier_postal_code || $invoice->supplier_city){{ implode(' ', array_filter([$invoice->supplier_postal_code, $invoice->supplier_city])) }}<br>@endif
            @if($invoice->supplier_country){{ $invoice->supplier_country }}<br>@endif
            <div style="margin-top:2mm;">
                @if($invoice->supplier_ico){{ __('app.invoice_pdf_ico') }}: {{ $invoice->supplier_ico }}<br>@endif
                @if($invoice->supplier_dic){{ __('app.invoice_pdf_dic') }}: {{ $invoice->supplier_dic }}<br>@endif
                @if($invoice->is_vat_payer && $invoice->supplier_vat_number){{ __('app.invoice_pdf_vat_number') }}: {{ $invoice->supplier_vat_number }}<br>@endif
            </div>
            @if($invoice->supplier_registration_info)
            <div class="muted" style="margin-top:2mm; font-size:9pt;">{{ $invoice->supplier_registration_info }}</div>
            @endif
        </td>
        <td>
            <div class="kg-label">{{ __('app.invoice_pdf_customer') }}</div>
            <div class="kg-name">{{ $invoice->customer_name }}</div>
            @if($invoice->customer_street){{ $invoice->customer_street }}<br>@endif
            @if($invoice->customer_postal_code || $invoice->customer_city){{ implode(' ', array_filter([$invoice->customer_postal_code, $invoice->customer_city])) }}<br>@endif
            @if($invoice->customer_country){{ $invoice->customer_country }}<br>@endif
            <div style="margin-top:2mm;">
                @if($invoice->customer_ico){{ __('app.invoice_pdf_ico') }}: {{ $invoice->customer_ico }}<br>@endif
                @if($invoice->customer_dic){{ __('app.invoice_pdf_dic') }}: {{ $invoice->customer_dic }}<br>@endif
                @if($invoice->customer_vat_number){{ __('app.invoice_pdf_vat_number') }}: {{ $invoice->customer_vat_number }}<br>@endif
            </div>
        </td>
    </tr>
</table>

<table class="parties block">
    <tr>
        <td>
            <div class="meta-lines">
                @if($invoice->variable_symbol)<div>{{ __('app.invoice_pdf_variable_symbol') }}: {{ $invoice->variable_symbol }}</div>@endif
                @if($invoice->constant_symbol)<div>{{ __('app.invoice_pdf_constant_symbol') }}: {{ $invoice->constant_symbol }}</div>@endif
                @if($invoice->specific_symbol)<div>{{ __('app.invoice_pdf_specific_symbol') }}: {{ $invoice->specific_symbol }}</div>@endif
                @if($invoice->payment_type)<div>{{ __('app.invoice_pdf_payment_type') }}: {{ $invoice->payment_type->label() }}</div>@endif
                @if($invoice->supplier_iban || $invoice->supplier_swift)
                <div>{{ __('app.invoice_pdf_iban') }} / {{ __('app.invoice_pdf_swift') }}: {{ $invoice->supplier_iban }}@if($invoice->supplier_iban && $invoice->supplier_swift) / @endif{{ $invoice->supplier_swift }}</div>
                @endif
            </div>
        </td>
        <td>
            <table class="meta">
                <tr><td>{{ __('app.invoice_pdf_issue_date') }}:</td><td class="num">{{ format_date($invoice->issue_date) }}</td></tr>
                <tr><td>{{ __('app.invoice_pdf_delivery_date') }}:</td><td class="num">{{ format_date($invoice->delivery_date) }}</td></tr>
                <tr><td>{{ __('app.invoice_pdf_due_date') }}:</td><td class="num">{{ format_date($invoice->due_date) }}</td></tr>
                @if($invoice->period_from || $invoice->period_to)
                <tr><td>{{ __('app.invoice_pdf_period') }}:</td><td class="num">{{ format_date($invoice->period_from) }} – {{ format_date($invoice->period_to) }}</td></tr>
                @endif
            </table>
        </td>
    </tr>
</table>

<table class="kg-items block">
    <thead>
        <tr>
            <th>{{ __('app.invoice_pdf_item_description') }}</th>
            <th class="num">{{ __('app.invoice_pdf_item_quantity') }}</th>
            <th>{{ __('app.invoice_pdf_item_unit') }}</th>
            <th class="num">{{ __('app.invoice_pdf_item_unit_price') }}</th>
            @if($hasDiscount)<th class="num">{{ __('app.invoice_pdf_discount') }}</th>@endif
            @if($invoice->is_vat_payer)<th class="num">{{ __('app.invoice_pdf_vat_rate') }}</th>@endif
            <th class="num">{{ __('app.invoice_pdf_item_total') }}</th>
        </tr>
    </thead>
    <tbody>
        @foreach($invoice->items as $item)
        <tr>
            <td>{{ $item->description }}</td>
            <td class="num">{{ format_quantity((float) $item->quantity) }}</td>
            <td>{{ $item->unit ?? '' }}</td>
            <td class="num">{{ $currencyEnum->format((float) $item->unit_price) }}</td>
            @if($hasDiscount)<td class="num">{{ (float) $item->discount_percent > 0 ? number_format((float) $item->discount_percent, 0)."\u{00A0}%" : '—' }}</td>@endif
            @if($invoice->is_vat_payer)<td class="num">{{ number_format((float) $item->vat_rate, 0) }}&nbsp;%</td>@endif
            <td class="num">{{ $currencyEnum->format((float) $item->line_base) }}</td>
        </tr>
        @endforeach
    </tbody>
</table>

<table class="parties block" style="page-break-inside:avoid;">
    <tr>
        <td>
            @if($invoice->note)
            <div class="kg-label">{{ __('app.invoice_pdf_note') }}</div>
            <div>{{ $invoice->note }}</div>
            @endif
            @if($signatureDataUri ?? null)
            <div style="margin-top:6mm;">
                <div class="kg-label">{{ __('app.invoice_pdf_signature') }}:</div>
                <div class="sig-frame"><img src="{{ $signatureDataUri }}" alt="" style="max-width:140px; max-height:82px; margin-top:4px;" /></div>
            </div>
            @endif
        </td>
        <td>
            <table class="sums">
                @if($invoice->is_vat_payer && !empty($invoice->vat_breakdown))
                <tr class="muted" style="font-size:9pt;">
                    <td>{{ __('app.invoice_vat_recap_rate') }}</td>
                    <td class="num">{{ __('app.invoice_vat_recap_base') }}</td>
                    <td class="num">{{ __('app.invoice_vat_recap_vat') }}</td>
                    <td class="num">{{ __('app.invoice_vat_recap_total') }}</td>
                </tr>
                @foreach($invoice->vat_breakdown as $line)
                <tr style="font-size:9.5pt;">
                    <td>{{ number_format((float) $line['rate'], 0) }}&nbsp;%</td>
                    <td class="num">{{ $currencyEnum->format((float) $line['base']) }}</td>
                    <td class="num">{{ $currencyEnum->format((float) $line['vat']) }}</td>
                    <td class="num">{{ $currencyEnum->format((float) $line['total']) }}</td>
                </tr>
                @endforeach
                <tr><td colspan="4" style="border-bottom:1px solid #E9ECEA; padding:2px 0;"></td></tr>
                @endif

                @if($invoice->is_vat_payer)
                <tr><td colspan="3">{{ __('app.invoice_pdf_subtotal') }}</td><td class="num">{{ $currencyEnum->format((float) $invoice->subtotal) }}</td></tr>
                <tr><td colspan="3">{{ __('app.invoice_pdf_vat_recap') }}</td><td class="num">{{ $currencyEnum->format((float) $invoice->vat_amount) }}</td></tr>
                @endif
                @if((float) $invoice->rounding_amount !== 0.0)
                <tr class="muted" style="font-size:9.5pt;"><td colspan="3">{{ __('app.invoice_pdf_rounding') }}</td><td class="num">{{ $currencyEnum->format((float) $invoice->rounding_amount) }}</td></tr>
                @endif
                <tr class="grand total"><td colspan="3">{{ __('app.invoice_pdf_total') }}</td><td class="num">{{ $currencyEnum->format((float) $invoice->total) }}</td></tr>
                @if((float) $invoice->deposit > 0)
                <tr><td colspan="3">{{ __('app.invoice_pdf_deposit') }}</td><td class="num">{{ $currencyEnum->format((float) $invoice->deposit) }}</td></tr>
                <tr class="total"><td colspan="3">{{ __('app.invoice_pdf_balance_due') }}</td><td class="num">{{ $currencyEnum->format($invoice->amount_payable) }}</td></tr>
                @endif
            </table>
        </td>
    </tr>
</table>

@if($qrDataUri || $invoice->supplier_iban)
<div class="block pay panel" style="padding:10px;">
    <table>
        <tr>
            @if($qrDataUri)
            <td style="width:130px;">
                <div class="muted" style="font-size:8pt; font-weight:500; margin-bottom:3px;">PAY by square</div>
                <img src="{{ $qrDataUri }}" width="112" height="112" alt="PAY by square QR" />
            </td>
            @endif
            @if($invoice->supplier_iban)
            <td>
                <table>
                    <tr>
                        <td class="pay-cell"><div class="l">{{ __('app.invoice_pdf_iban') }}</div><div class="v">{{ $invoice->supplier_iban }}</div></td>
                        <td class="pay-cell"><div class="l">{{ __('app.invoice_pdf_variable_symbol') }}</div><div class="v">{{ $invoice->variable_symbol ?? '—' }}</div></td>
                    </tr>
                    <tr>
                        <td class="pay-cell"><div class="l">{{ __('app.invoice_pdf_due_date') }}</div><div class="v">{{ format_date($invoice->due_date) }}</div></td>
                        <td class="pay-cell"><div class="l">{{ __('app.invoice_pdf_amount_due') }}</div><div class="v">{{ $currencyEnum->format($amountDue) }}</div></td>
                    </tr>
                </table>
            </td>
            @endif
        </tr>
    </table>
</div>
@endif

@if(!$invoice->is_vat_payer)
<p class="muted" style="font-size:9.5pt; margin-top:5mm;">{{ __('app.invoice_pdf_non_vat_payer_clause') }}</p>
@endif
@if($invoice->footer_text)
<div class="muted" style="margin-top:4mm; font-size:9.5pt;">{{ $invoice->footer_text }}</div>
@endif

@if($footerHtml ?? null)
{!! $footerHtml !!}
@endif

</body>
</html>
