@php
    $currencyEnum = $quote->currency;
    $hasDiscount = $quote->items->contains(fn ($item) => (float) $item->discount_percent > 0);
    $street = $quote->client?->street ?? $quote->customer_street;
    $city = $quote->client?->city ?? $quote->customer_city;
    $postalCode = $quote->client?->postal_code ?? $quote->customer_postal_code;
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>{{ __('app.quote_pdf_title') }} {{ $quote->number ?? __('app.quote_pdf_draft') }}</title>
    @include('pdf.partials.kegos-base')
    <style>
        .parties td { vertical-align: top; padding: 0; width: 50%; }
        .parties td + td { padding-left: 8mm; }
        .block { margin-top: 5mm; }
        .sums td { padding: 3px 0; }
        .sums .grand td { padding-top: 6px; border-top: 1px solid #111C18; font-size: 13pt; }
    </style>
</head>
<body>

@include('pdf.partials.kegos-header', [
    'documentType' => __('app.quote_pdf_title'),
    'number' => $quote->number ?? __('app.quote_pdf_draft'),
])

<table class="parties">
    <tr>
        <td>
            <div class="kg-label">{{ __('app.quote_pdf_supplier') }}</div>
            <div class="kg-name">{{ $tenant->name }}</div>
            @if($tenant->address_line){{ $tenant->address_line }}<br>@endif
            @if($tenant->city || $tenant->postal_code){{ implode(' ', array_filter([$tenant->postal_code, $tenant->city])) }}<br>@endif
            @if($tenant->country){{ $tenant->country }}<br>@endif
            <div style="margin-top:2mm;">
                @if($tenant->ico){{ __('app.quote_pdf_ico') }}: {{ $tenant->ico }}<br>@endif
                @if($tenant->dic){{ __('app.quote_pdf_dic') }}: {{ $tenant->dic }}<br>@endif
            </div>
        </td>
        <td>
            <div class="kg-label">{{ __('app.quote_pdf_customer') }}</div>
            <div class="kg-name">{{ $quote->client?->name ?? $quote->customer_name }}</div>
            @if($street){{ $street }}<br>@endif
            @if($city || $postalCode){{ implode(' ', array_filter([$postalCode, $city])) }}<br>@endif
            @if($quote->cleaningObject)
            <div style="margin-top:2mm;"><span class="muted">{{ __('app.quote_pdf_object') }}:</span> {{ $quote->cleaningObject->name }}</div>
            @endif
        </td>
    </tr>
</table>

<table class="block" style="width:auto; font-size:9.5pt;">
    <tr>
        <td class="muted" style="padding-right:8px;">{{ __('app.quote_pdf_issue_date') }}:</td>
        <td class="num" style="padding-right:24px;">{{ format_date($quote->issue_date) }}</td>
        <td class="muted" style="padding-right:8px;">{{ __('app.quote_pdf_valid_until') }}:</td>
        <td class="num" style="padding-right:24px;">{{ format_date($quote->valid_until) }}</td>
        <td class="muted" style="padding-right:8px;">{{ __('app.quote_pdf_status') }}:</td>
        <td>{{ $quote->status->label() }}</td>
    </tr>
</table>

<table class="kg-items block">
    <thead>
        <tr>
            <th>{{ __('app.quote_pdf_item_description') }}</th>
            <th class="num">{{ __('app.quote_pdf_item_quantity') }}</th>
            <th>{{ __('app.quote_pdf_item_unit') }}</th>
            <th class="num">{{ __('app.quote_pdf_item_unit_price') }}</th>
            @if($hasDiscount)<th class="num">{{ __('app.quote_pdf_discount') }}</th>@endif
            @if($quote->is_vat_payer)<th class="num">{{ __('app.quote_pdf_vat_rate') }}</th>@endif
            <th class="num">{{ __('app.quote_pdf_item_total') }}</th>
        </tr>
    </thead>
    <tbody>
        @foreach($quote->items as $item)
        <tr>
            <td>
                {{ $item->description }}
                @if($item->frequency)<br><span class="muted" style="font-size:9pt;">{{ $item->frequency->label() }}</span>@endif
                @if($item->note)<br><span class="muted" style="font-size:9pt;">{{ $item->note }}</span>@endif
            </td>
            <td class="num">{{ format_quantity((float) $item->quantity) }}</td>
            <td>{{ $item->unit ?? '' }}</td>
            <td class="num">{{ $currencyEnum->format((float) $item->unit_price) }}</td>
            @if($hasDiscount)<td class="num">{{ (float) $item->discount_percent > 0 ? number_format((float) $item->discount_percent, 0)."\u{00A0}%" : '—' }}</td>@endif
            @if($quote->is_vat_payer)<td class="num">{{ number_format((float) $item->vat_rate, 0) }}&nbsp;%</td>@endif
            <td class="num">{{ $currencyEnum->format((float) $item->line_base) }}</td>
        </tr>
        @endforeach
    </tbody>
</table>

<table class="parties block" style="page-break-inside:avoid;">
    <tr>
        <td>
            @if($quote->note)
            <div class="kg-label">{{ __('app.quote_pdf_note') }}</div>
            <div>{{ $quote->note }}</div>
            @endif
        </td>
        <td>
            <table class="sums">
                @if($quote->is_vat_payer && !empty($quote->vat_breakdown))
                <tr class="muted" style="font-size:9pt;">
                    <td>{{ __('app.invoice_vat_recap_rate') }}</td>
                    <td class="num">{{ __('app.invoice_vat_recap_base') }}</td>
                    <td class="num">{{ __('app.invoice_vat_recap_vat') }}</td>
                    <td class="num">{{ __('app.invoice_vat_recap_total') }}</td>
                </tr>
                @foreach($quote->vat_breakdown as $line)
                <tr style="font-size:9.5pt;">
                    <td>{{ number_format((float) $line['rate'], 0) }}&nbsp;%</td>
                    <td class="num">{{ $currencyEnum->format((float) $line['base']) }}</td>
                    <td class="num">{{ $currencyEnum->format((float) $line['vat']) }}</td>
                    <td class="num">{{ $currencyEnum->format((float) $line['total']) }}</td>
                </tr>
                @endforeach
                <tr><td colspan="4" style="border-bottom:1px solid #E9ECEA; padding:2px 0;"></td></tr>
                @endif
                @if($quote->is_vat_payer)
                <tr><td colspan="3">{{ __('app.invoice_pdf_subtotal') }}</td><td class="num">{{ $currencyEnum->format((float) $quote->subtotal) }}</td></tr>
                <tr><td colspan="3">{{ __('app.invoice_pdf_vat_recap') }}</td><td class="num">{{ $currencyEnum->format((float) $quote->vat_amount) }}</td></tr>
                @endif
                <tr class="grand total"><td colspan="3">{{ __('app.invoice_pdf_total') }}</td><td class="num">{{ $currencyEnum->format((float) $quote->total) }}</td></tr>
            </table>
        </td>
    </tr>
</table>

@if(!$quote->is_vat_payer)
<p class="muted" style="font-size:9.5pt; margin-top:5mm;">{{ __('app.invoice_pdf_non_vat_payer_clause') }}</p>
@endif

</body>
</html>
