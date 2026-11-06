@php
    $display = app(\App\Services\Documents\QuoteDisplay::class);
    $client = $quote->client;
    $city = $tenant->city;
    $place = filled($tenant->signing_place) ? $tenant->signing_place : $city;
    $dated = filled($place)
        ? $place.', '.__('app.quote_pdf_dated').' '.format_date($quote->issue_date)
        : \Illuminate\Support\Str::ucfirst(__('app.quote_pdf_dated')).' '.format_date($quote->issue_date);
    $includedLines = $quote->includedLines();
    $groups = $display->groups($quote);
    $customerStreet = $client?->street ?? $quote->customer_street;
    $customerCity = $client?->city ?? $quote->customer_city;
    $customerPostalCode = $client?->postal_code ?? $quote->customer_postal_code;
    $locality = implode(' ', array_filter([$tenant->postal_code, $tenant->city]));
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>{{ __('app.quote_pdf_title') }} {{ $quote->number ?? __('app.quote_pdf_draft') }}</title>
    @include('pdf.partials.kegos-base')
    <style>
        body { font-size: 10pt; }
        .q-top td { vertical-align: middle; padding: 1mm 0 0; }
        .q-logo svg { display: block; height: 5mm; width: 45.63mm; overflow: visible; }
        .q-doctype { text-align: right; font-size: 8pt; letter-spacing: .22em; color: #5A6661; font-weight: 500; text-transform: uppercase; }
        .q-docno { text-align: right; font-size: 8.5pt; color: #5A6661; margin-top: 1mm; }
        h1 { font-size: 20pt; font-weight: 600; letter-spacing: -0.03em; margin: 6mm 0 1.5mm; line-height: 1.1; }
        .q-subtitle { font-size: 12.5pt; color: #5A6661; margin: 0 0 5mm; }
        .q-rule { border-top: 3pt solid #FF5A1F; width: 18mm; margin: 0 0 5mm; }
        .q-label { font-size: 7.5pt; letter-spacing: .2em; font-weight: 600; color: #FF5A1F; text-transform: uppercase; margin-bottom: 2.5mm; }
        .q-parties { margin-top: 4mm; }
        .q-parties td { width: 50%; vertical-align: top; padding: 0; line-height: 1.6; color: #5A6661; }
        .q-parties .q-name { font-size: 11.5pt; font-weight: 600; color: #111C18; margin-bottom: .5mm; }
        .q-meta { margin-top: 4mm; font-size: 9pt; color: #5A6661; }
        .q-meta b { color: #111C18; font-weight: 500; }
        .q-section { margin-top: 7mm; }
        .q-items th { font-size: 8pt; font-weight: 500; color: #5A6661; letter-spacing: .06em; text-align: left; padding: 0 0 2.5mm; border-bottom: 1px solid #C9CFCC; }
        .q-items td { padding: 3mm 0; border-bottom: 1px solid #E9ECEA; vertical-align: top; }
        .q-items td + td, .q-items th + th { padding-left: 3mm; }
        .q-items .num { text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; }
        .q-items .qty { text-align: center; white-space: nowrap; color: #5A6661; width: 22mm; }
        .q-items .unit { color: #5A6661; width: 42mm; }
        .q-items .tot { font-weight: 600; width: 36mm; }
        .q-iname { font-weight: 600; font-size: 10.5pt; }
        .q-idesc { color: #5A6661; font-size: 9pt; margin-top: 1.2mm; padding-right: 6mm; white-space: pre-line; }
        .q-per { font-weight: 400; color: #5A6661; }
        .q-after { margin-top: 5mm; page-break-inside: avoid; }
        .q-after > tbody > tr > td { vertical-align: top; padding: 0; width: 50%; }
        .q-sums td { padding: 1.6mm 0; }
        .q-sums td.v { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
        .q-sums tr.sub td { padding: .5mm 0; font-size: 9pt; color: #5A6661; }
        .q-sums tr.grp td { font-weight: 600; font-size: 11pt; }
        .q-sums tr.grp.sep td { border-top: 1px solid #111C18; padding-top: 2.4mm; }
        .q-note { font-size: 8.5pt; color: #5A6661; text-align: right; margin-top: 2mm; }
        ul.q-inc { list-style: none; padding: 0; margin: 0; }
        ul.q-inc li { padding-left: 6mm; position: relative; margin-bottom: 1.8mm; font-size: 10.5pt; }
        ul.q-inc li::before { content: ''; position: absolute; left: 0; top: 1.6mm; width: 2.3mm; height: 2.3mm; border-radius: 50%; background: #FF5A1F; }
        .q-closing { margin-top: 7mm; color: #5A6661; line-height: 1.7; page-break-inside: avoid; }
        .q-closing b { color: #111C18; font-weight: 600; }
        .q-keep { page-break-inside: avoid; }
    </style>
</head>
<body>

<table class="q-top">
    <tr>
        <td class="q-logo">{!! file_get_contents(resource_path('images/brand/kegos-logo.svg')) !!}</td>
        <td>
            <div class="q-doctype">{{ __('app.quote_pdf_title') }}</div>
            @if($quote->number)<div class="q-docno">{{ $quote->number }}</div>@endif
        </td>
    </tr>
</table>

<h1>{{ __('app.quote_pdf_heading') }}</h1>
@if($quote->subject)<p class="q-subtitle">{{ $quote->subject }}</p>@endif
<div class="q-rule"></div>

<table class="q-parties">
    <tr>
        <td>
            <div class="q-label">{{ __('app.quote_pdf_provider') }}</div>
            <div class="q-name">{{ $tenant->name }}</div>
            @if($tenant->address_line){{ $tenant->address_line }}<br>@endif
            @if($locality){{ $locality }}<br>@endif
            @if($tenant->ico){{ __('app.quote_pdf_ico') }}: {{ $tenant->ico }}<br>@endif
            @if($tenant->dic){{ __('app.quote_pdf_dic') }}: {{ $tenant->dic }}@endif
        </td>
        <td>
            <div class="q-label">{{ __('app.quote_pdf_orderer') }}</div>
            <div class="q-name">{{ $client?->name ?? $quote->customer_name }}</div>
            @if($customerStreet){{ $customerStreet }}<br>@endif
            @if($customerCity || $customerPostalCode){{ implode(' ', array_filter([$customerPostalCode, $customerCity])) }}<br>@endif
            @if($client?->ico){{ __('app.quote_pdf_ico') }}: {{ $client->ico }}<br>@endif
            @if($client?->dic){{ __('app.quote_pdf_dic') }}: {{ $client->dic }}<br>@endif
            @if($quote->cleaningObject){{ __('app.quote_pdf_object') }}: {{ $quote->cleaningObject->name }}@endif
        </td>
    </tr>
</table>

<div class="q-meta">{{ __('app.quote_pdf_issue_date') }}: <b>{{ format_date($quote->issue_date) }}</b> &nbsp;·&nbsp; {{ __('app.quote_pdf_valid_until_long') }}: <b>{{ format_date($quote->valid_until) }}</b></div>

<div class="q-section">
    <div class="q-label">{{ __('app.quote_pdf_offer') }}</div>
    <table class="q-items">
        <thead>
            <tr>
                <th>{{ __('app.quote_pdf_col_service') }}</th>
                <th class="qty">{{ __('app.quote_pdf_item_quantity') }}</th>
                <th class="num unit">{{ __('app.quote_pdf_col_unit_price') }}</th>
                <th class="num tot">{{ __('app.quote_pdf_col_line_total') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach($quote->items as $item)
            <tr class="q-keep">
                <td>
                    <div class="q-iname">{{ $item->description }}</div>
                    @if($item->note)<div class="q-idesc">{{ $item->note }}</div>@endif
                </td>
                <td class="qty">{{ $display->quantity($item) }}</td>
                <td class="num unit">{{ $display->unitPrice($quote, $item) }}{{ $display->discount($item) }}</td>
                <td class="num tot">{{ $display->lineAmount($quote, $item) }}@if($display->lineSuffix($item)) <span class="q-per">/ {{ $display->lineSuffix($item) }}</span>@endif</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <table class="q-after">
        <tr>
            <td style="padding-top: 2mm; padding-right: 6mm;">
                @if($includedLines !== [])
                <div class="q-label">{{ __('app.quote_pdf_included') }}</div>
                <ul class="q-inc">
                    @foreach($includedLines as $line)<li>{{ $line }}</li>@endforeach
                </ul>
                @endif
            </td>
            <td>
                <table class="q-sums">
                    @foreach($groups as $group)
                    @if($quote->is_vat_payer)
                    <tr class="sub"><td>{{ __('app.quote_pdf_excl_vat') }}</td><td class="v">{{ $display->money($quote, $group['subtotal']) }} &nbsp;+&nbsp; {{ __('app.document_quote_vat') }} {{ $display->money($quote, $group['vat_amount']) }}</td></tr>
                    @endif
                    <tr class="grp{{ $loop->first ? '' : ' sep' }}"><td>{{ $group['period']->label() }}@if($quote->is_vat_payer) {{ __('app.quote_pdf_incl_vat') }}@endif</td><td class="v">{{ $display->money($quote, $group['total']) }}@if($group['period']->shortSuffix()) <span class="q-per" style="font-size:10pt">/ {{ $group['period']->shortSuffix() }}</span>@endif</td></tr>
                    @endforeach
                </table>
                @if(!$quote->is_vat_payer)
                <div class="q-note">{{ __('app.quote_pdf_non_vat_clause') }}</div>
                @endif
            </td>
        </tr>
    </table>
</div>

@if($quote->note)
<div class="q-section q-keep">
    <div class="q-label">{{ __('app.quote_pdf_note') }}</div>
    <div style="white-space: pre-line;">{{ $quote->note }}</div>
</div>
@endif

<div class="q-closing">
    {{ $dated }}<br>
    <span style="display:block;height:3mm"></span>
    {{ __('app.quote_pdf_regards') }}<br>
    <b>{{ $tenant->name }}</b>
</div>

</body>
</html>
