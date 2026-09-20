@php
    $hasDiscount = $invoice->items->contains(fn ($item) => (float) $item->discount_percent > 0);
    $currencyEnum = $invoice->currency ?? \App\Enums\CurrencyEnum::EUR;
    $amountDue = (float) $invoice->deposit > 0 ? (float) $invoice->balance_due : (float) $invoice->total;
    // Owner decision: banner only for Draft/Cancelled. Overdue is payment-timing state, not a
    // print-time state — surfacing it here would make the printout depend on the moment of
    // download rather than the invoice snapshot at issue. Overdue already surfaces via the
    // InvoiceOverdue notification + dashboard alert.
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
    <title>{{ __('app.invoice_pdf_title') }} {{ $invoice->number ?? __('app.invoice_pdf_draft') }}</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: "Open Sans", "DejaVu Sans", Arial, sans-serif; font-size: 11px; line-height: 1.45; color: #222; margin: 0; padding: 0; }
        @media screen { body { padding: 12mm 15mm; } }
        table { width: 100%; border-collapse: collapse; }

        .grid { table-layout: fixed; }
        .grid > tbody > tr > td { vertical-align: top; padding: 0; }
        .col-l { width: 50%; border-right: 1px dotted #9a9a9a; padding-right: 18px; }
        .col-r { width: 50%; padding-left: 22px; }
        .row { padding: 14px 0; }
        .row + .row, .rule { border-top: 1px dotted #9a9a9a; }
        .hr { border-top: 1px dotted #9a9a9a; margin: 0; }

        .label { font-size: 8.5px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.2px; color: #222; margin-bottom: 2px; }
        .name { font-size: 14px; margin-bottom: 6px; }
        .title { font-size: 28px; font-weight: 400; margin: 0 0 4px; text-align: left; }

        .items th { font-weight: 400; font-size: 10px; padding: 10px 6px; border-top: 1px solid #333; border-bottom: 1px solid #333; text-align: left; }
        .items td { padding: 10px 6px; border-bottom: 1px solid #333; }
        .items tr.not-last td { border-bottom-color: #e5e5e5; }

        .sig-label { font-size: 9px; color: #333; vertical-align: top; }
        .sig-frame { display: inline-block; width: 150px; height: 100px; border: 1px solid #e0e0e0; text-align: center; vertical-align: top; margin-left: 12px; }
    </style>
</head>
<body>

{{-- Block A: header grid — supplier + bank (left) / title + customer + dates (right) --}}
<table class="grid">
    <tr>
        <td class="col-l">
            <div class="row">
                <div class="label">{{ __('app.invoice_pdf_supplier') }}:</div>
                <div class="name">{{ $invoice->supplier_name }}</div>
                @if($invoice->supplier_address_line)
                    {{ $invoice->supplier_address_line }}<br>
                @endif
                @if($invoice->supplier_postal_code || $invoice->supplier_city)
                    {{ implode(' ', array_filter([$invoice->supplier_postal_code, $invoice->supplier_city])) }}<br>
                @endif
                @if($invoice->supplier_country)
                    {{ $invoice->supplier_country }}<br>
                @endif
                <br>
                @if($invoice->supplier_ico)
                    {{ __('app.invoice_pdf_ico') }}: {{ $invoice->supplier_ico }}<br>
                @endif
                @if($invoice->supplier_dic)
                    {{ __('app.invoice_pdf_dic') }}: {{ $invoice->supplier_dic }}<br>
                @endif
                @if($invoice->is_vat_payer && $invoice->supplier_vat_number)
                    {{ __('app.invoice_pdf_vat_number') }}: {{ $invoice->supplier_vat_number }}<br>
                @endif
            </div>
            <div class="row">
                @if($invoice->supplier_iban || $invoice->supplier_swift)
                    {{ __('app.invoice_pdf_iban') }} / {{ __('app.invoice_pdf_swift') }}:
                    {{ $invoice->supplier_iban }}@if($invoice->supplier_iban && $invoice->supplier_swift) / @endif{{ $invoice->supplier_swift }}<br>
                @endif
                @if($invoice->variable_symbol)
                    {{ __('app.invoice_pdf_variable_symbol') }}: {{ $invoice->variable_symbol }}<br>
                @endif
                @if($invoice->constant_symbol)
                    {{ __('app.invoice_pdf_constant_symbol') }}: {{ $invoice->constant_symbol }}<br>
                @endif
                @if($invoice->specific_symbol)
                    {{ __('app.invoice_pdf_specific_symbol') }}: {{ $invoice->specific_symbol }}<br>
                @endif
                @if($invoice->supplier_registration_info)
                    <br><span style="font-size:10px; color:#777;">{{ $invoice->supplier_registration_info }}</span>
                @endif
            </div>
        </td>
        <td class="col-r">
            <div class="row">
                <p class="title">{{ __('app.invoice_pdf_title') }} {{ $invoice->number ?? __('app.invoice_pdf_draft') }}</p>
                @if($showStatusBanner)
                <div style="display:inline-block; padding:4px 10px; border:2px solid #b91c1c; color:#b91c1c; font-size:11px; font-weight:bold; text-transform:uppercase; letter-spacing:1px;">
                    {{ __('app.invoice_pdf_status') }}: {{ $invoice->status->label() }}
                </div>
                @endif
            </div>
            <div class="row">
                <div class="label">{{ __('app.invoice_pdf_customer') }}:</div>
                <div class="name">{{ $invoice->customer_name }}</div>
                @if($invoice->customer_street)
                    {{ $invoice->customer_street }}<br>
                @endif
                @if($invoice->customer_postal_code || $invoice->customer_city)
                    {{ implode(' ', array_filter([$invoice->customer_postal_code, $invoice->customer_city])) }}<br>
                @endif
                @if($invoice->customer_country)
                    {{ $invoice->customer_country }}<br>
                @endif
            </div>
            <div class="row">
                <table style="table-layout:fixed;">
                    <tr>
                        <td style="width:50%; vertical-align:top; padding-right:12px;">
                            @if($invoice->customer_ico)
                                {{ __('app.invoice_pdf_ico') }}: {{ $invoice->customer_ico }}<br>
                            @endif
                            @if($invoice->customer_dic)
                                {{ __('app.invoice_pdf_dic') }}: {{ $invoice->customer_dic }}<br>
                            @endif
                            @if($invoice->customer_vat_number)
                                {{ __('app.invoice_pdf_vat_number') }}: {{ $invoice->customer_vat_number }}<br>
                            @endif
                        </td>
                        <td style="width:50%; vertical-align:top;">
                            <table style="table-layout:fixed;">
                                <tr>
                                    <td style="white-space:nowrap; font-size:9.5px; padding-right:6px;">{{ __('app.invoice_pdf_issue_date') }}:</td>
                                    <td style="font-size:9.5px;">{{ $invoice->issue_date ? $invoice->issue_date->format('d.m.Y') : '—' }}</td>
                                </tr>
                                <tr>
                                    <td style="white-space:nowrap; font-size:9.5px; padding-right:6px;">{{ __('app.invoice_pdf_delivery_date') }}:</td>
                                    <td style="font-size:9.5px;">{{ $invoice->delivery_date ? $invoice->delivery_date->format('d.m.Y') : '—' }}</td>
                                </tr>
                                <tr>
                                    <td style="white-space:nowrap; font-size:9.5px; padding-right:6px;">{{ __('app.invoice_pdf_due_date') }}:</td>
                                    <td style="font-size:9.5px;">{{ $invoice->due_date ? $invoice->due_date->format('d.m.Y') : '—' }}</td>
                                </tr>
                                @if($invoice->payment_type)
                                <tr>
                                    <td style="white-space:nowrap; font-size:9.5px; padding-right:6px;">{{ __('app.invoice_pdf_payment_type') }}:</td>
                                    <td style="font-size:9.5px;">{{ $invoice->payment_type->label() }}</td>
                                </tr>
                                @endif
                            </table>
                            @if($invoice->period_from || $invoice->period_to)
                            <div style="margin-top:4px; font-size:9.5px;">
                                <span style="white-space:nowrap;">{{ __('app.invoice_pdf_period') }}:</span>
                                {{ $invoice->period_from ? $invoice->period_from->format('d.m.Y') : '—' }}
                                –
                                {{ $invoice->period_to ? $invoice->period_to->format('d.m.Y') : '—' }}
                            </div>
                            @endif
                        </td>
                    </tr>
                </table>
            </div>
        </td>
    </tr>
</table>
<div class="hr"></div>

{{-- Block B: items table --}}
<table class="items" style="margin-top:18px;">
    <thead>
        <tr>
            <th>{{ __('app.invoice_pdf_item_description') }}</th>
            <th style="text-align:right; white-space:nowrap;">{{ __('app.invoice_pdf_item_quantity') }}</th>
            <th>{{ __('app.invoice_pdf_item_unit') }}</th>
            <th style="text-align:right; white-space:nowrap;">{{ __('app.invoice_pdf_item_unit_price') }}</th>
            @if($hasDiscount)
            <th style="text-align:right; white-space:nowrap;">{{ __('app.invoice_pdf_discount') }}</th>
            @endif
            @if($invoice->is_vat_payer)
            <th style="text-align:right; white-space:nowrap;">{{ __('app.invoice_pdf_vat_rate') }}</th>
            @endif
            <th style="text-align:right; white-space:nowrap;">{{ __('app.invoice_pdf_item_total') }}</th>
        </tr>
    </thead>
    <tbody>
        @foreach($invoice->items as $item)
        <tr @if(!$loop->last) class="not-last" @endif>
            <td>{{ $item->description }}</td>
            <td style="text-align:right;">{{ format_quantity((float) $item->quantity) }}</td>
            <td>{{ $item->unit ?? '' }}</td>
            <td style="text-align:right;">{{ $currencyEnum->format((float) $item->unit_price) }}</td>
            @if($hasDiscount)
            <td style="text-align:right;">{{ (float) $item->discount_percent > 0 ? number_format((float) $item->discount_percent, 0) . ' %' : '—' }}</td>
            @endif
            @if($invoice->is_vat_payer)
            <td style="text-align:right;">{{ number_format((float) $item->vat_rate, 0) }} %</td>
            @endif
            <td style="text-align:right;">{{ $currencyEnum->format((float) $item->line_base) }}</td>
        </tr>
        @endforeach
    </tbody>
</table>

{{-- Block C: note + QR (left) / totals + signature (right) --}}
<table class="grid" style="margin-top:18px;">
    <tr>
        <td class="col-l">
            @if($invoice->note)
            <div class="row">
                <strong>{{ __('app.invoice_pdf_note') }}:</strong> {{ $invoice->note }}
            </div>
            @endif
        </td>
        <td class="col-r">
            <div class="row">
                <table>
                    @if($invoice->is_vat_payer && !empty($invoice->vat_breakdown))
                    <tr style="background:#f9f9f9;">
                        <td style="padding:3px 8px; font-size:10px; color:#666;">{{ __('app.invoice_vat_recap_title') }}</td>
                        <td style="padding:3px 8px; font-size:10px; color:#666; text-align:center;">{{ __('app.invoice_vat_recap_rate') }}</td>
                        <td style="padding:3px 8px; font-size:10px; color:#666; text-align:right;">{{ __('app.invoice_vat_recap_base') }}</td>
                        <td style="padding:3px 8px; font-size:10px; color:#666; text-align:right;">{{ __('app.invoice_vat_recap_vat') }}</td>
                        <td style="padding:3px 8px; font-size:10px; color:#666; text-align:right;">{{ __('app.invoice_vat_recap_total') }}</td>
                    </tr>
                    @foreach($invoice->vat_breakdown as $line)
                    <tr>
                        <td style="padding:3px 8px; font-size:10px;"></td>
                        <td style="padding:3px 8px; font-size:10px; text-align:center;">{{ number_format((float) $line['rate'], 0) }} %</td>
                        <td style="padding:3px 8px; font-size:10px; text-align:right;">{{ $currencyEnum->format((float) $line['base']) }}</td>
                        <td style="padding:3px 8px; font-size:10px; text-align:right;">{{ $currencyEnum->format((float) $line['vat']) }}</td>
                        <td style="padding:3px 8px; font-size:10px; text-align:right;">{{ $currencyEnum->format((float) $line['total']) }}</td>
                    </tr>
                    @endforeach
                    <tr><td colspan="5" style="border-bottom:1px solid #eee;"></td></tr>
                    @endif

                    @if($invoice->is_vat_payer)
                    <tr>
                        <td colspan="4" style="padding:4px 8px;">{{ __('app.invoice_pdf_subtotal') }}</td>
                        <td style="text-align:right; padding:4px 8px;">{{ $currencyEnum->format((float) $invoice->subtotal) }}</td>
                    </tr>
                    <tr>
                        <td colspan="4" style="padding:4px 8px;">{{ __('app.invoice_pdf_vat_recap') }}</td>
                        <td style="text-align:right; padding:4px 8px;">{{ $currencyEnum->format((float) $invoice->vat_amount) }}</td>
                    </tr>
                    @endif
                    @if((float) $invoice->rounding_amount !== 0.0)
                    <tr>
                        <td colspan="{{ $invoice->is_vat_payer ? 4 : 1 }}" style="padding:4px 8px; color:#666; font-size:10px;">{{ __('app.invoice_pdf_rounding') }}</td>
                        <td style="text-align:right; padding:4px 8px; color:#666; font-size:10px;">{{ $currencyEnum->format((float) $invoice->rounding_amount) }}</td>
                    </tr>
                    @endif
                    <tr style="font-weight:bold; font-size:15px; border-top:2px solid #333;">
                        <td colspan="{{ $invoice->is_vat_payer ? 4 : 1 }}" style="padding:6px 8px;">{{ __('app.invoice_pdf_total') }}</td>
                        <td style="text-align:right; padding:6px 8px;">{{ $currencyEnum->format((float) $invoice->total) }}</td>
                    </tr>
                    @if((float) $invoice->deposit > 0)
                    <tr>
                        <td colspan="{{ $invoice->is_vat_payer ? 4 : 1 }}" style="padding:4px 8px;">{{ __('app.invoice_pdf_deposit') }}</td>
                        <td style="text-align:right; padding:4px 8px;">{{ $currencyEnum->format((float) $invoice->deposit) }}</td>
                    </tr>
                    <tr style="font-weight:bold; border-top:1px solid #aaa;">
                        <td colspan="{{ $invoice->is_vat_payer ? 4 : 1 }}" style="padding:6px 8px;">{{ __('app.invoice_pdf_balance_due') }}</td>
                        <td style="text-align:right; padding:6px 8px;">{{ $currencyEnum->format((float) $invoice->balance_due) }}</td>
                    </tr>
                    @endif
                </table>
            </div>
            @if($signatureDataUri ?? null)
            <div class="row">
                <div style="display:table; width:100%;">
                    <span class="sig-label">{{ __('app.invoice_pdf_signature') }}:</span>
                    <div class="sig-frame">
                        <img src="{{ $signatureDataUri }}" alt="" style="max-width:140px; max-height:92px; margin-top:4px;" />
                    </div>
                </div>
            </div>
            @endif
        </td>
    </tr>
</table>

{{-- Block D: QR box + payment strip (overlapping, spans both columns) --}}
@if($qrDataUri || $invoice->supplier_iban)
<div style="position:relative; margin-top:14px; page-break-inside:avoid; min-height:165px;">
    {{-- QR box (padding 8px+6px + label line + 130px image + 1px border) renders ~161px tall; min-height must clear that or trailing content (the non-VAT-payer clause) starts before the QR box visually ends, since absolutely-positioned children never grow their relative ancestor. --}}
    @if($qrDataUri)
    <div style="position:absolute; left:0; top:0; z-index:2; background:#fff; border:1px solid #d9d9d9; padding:8px 8px 6px; width:150px;">
        <div style="font-size:9px; font-weight:700; color:#555; margin-bottom:4px;">PAY by square</div>
        <img src="{{ $qrDataUri }}" width="130" height="130" alt="PAY by square QR" />
    </div>
    @endif
    @if($invoice->supplier_iban)
    <div style="position:absolute; left:{{ $qrDataUri ? '166px' : '0' }}; right:0; top:58px; height:56px; background:#dbeaf5;">
        <table style="height:100%;">
            <tr>
                <td style="padding:10px 14px; width:25%; text-align:center; border-right:1px dashed #b8d4e8;">
                    <div style="font-size:8.5px; color:#4a6f8f; text-transform:uppercase;">{{ __('app.invoice_pdf_iban') }}</div>
                    <div style="font-size:12px; font-weight:bold; color:#123b5a;">{{ $invoice->supplier_iban }}</div>
                </td>
                <td style="padding:10px 14px; width:25%; text-align:center; border-right:1px dashed #b8d4e8;">
                    <div style="font-size:8.5px; color:#4a6f8f; text-transform:uppercase;">{{ __('app.invoice_pdf_variable_symbol') }}</div>
                    <div style="font-size:12px; font-weight:bold; color:#123b5a;">{{ $invoice->variable_symbol ?? '—' }}</div>
                </td>
                <td style="padding:10px 14px; width:25%; text-align:center; border-right:1px dashed #b8d4e8;">
                    <div style="font-size:8.5px; color:#4a6f8f; text-transform:uppercase;">{{ __('app.invoice_pdf_due_date') }}</div>
                    <div style="font-size:12px; font-weight:bold; color:#123b5a;">{{ $invoice->due_date ? $invoice->due_date->format('d.m.Y') : '—' }}</div>
                </td>
                <td style="padding:10px 14px; width:25%; text-align:center;">
                    <div style="font-size:8.5px; color:#4a6f8f; text-transform:uppercase;">{{ __('app.invoice_pdf_amount_due') }}</div>
                    <div style="font-size:12px; font-weight:bold; color:#123b5a;">{{ $currencyEnum->format($amountDue) }}</div>
                </td>
            </tr>
        </table>
        @foreach([25, 50, 75] as $seam)
        <div style="position:absolute; left:{{ $seam }}%; top:-6px; width:12px; height:12px; margin-left:-6px; background:#fff; border-radius:50%;"></div>
        <div style="position:absolute; left:{{ $seam }}%; bottom:-6px; width:12px; height:12px; margin-left:-6px; background:#fff; border-radius:50%;"></div>
        @endforeach
    </div>
    @endif
</div>
@endif

{{-- Block E: clauses --}}
@if($invoice->header_text)
<div style="margin-top:12px; font-size:11px; color:#444;">{{ $invoice->header_text }}</div>
@endif
@if(!$invoice->is_vat_payer)
<p style="font-size:10px; color:#555; margin-top:12px;">{{ __('app.invoice_pdf_non_vat_payer_clause') }}</p>
@endif
@if($invoice->footer_text)
<div style="margin-top:10px; font-size:10px; color:#555;">{{ $invoice->footer_text }}</div>
@endif

{{-- Chrome renders footerView() as a pinned page footer at print time (see InvoicePdfService);
     the HTML preview has no such mechanism, so the same markup is rendered here, in-flow, at the
     position it occupies on the page. --}}
@if($footerHtml ?? null)
{!! $footerHtml !!}
@endif

</body>
</html>
