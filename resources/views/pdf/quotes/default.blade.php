<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>{{ __('app.quote_pdf_title') }} {{ $quote->number ?? __('app.quote_pdf_draft') }}</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 12px; color: #222; margin: 0; padding: 30px; }
        h1 { font-size: 22px; color: #1a1a1a; margin-bottom: 4px; }
        .quote-meta { color: #555; font-size: 11px; margin-bottom: 24px; }
        table { width: 100%; }
    </style>
</head>
<body>
    <h1>{{ __('app.quote_pdf_title') }}</h1>
    <div class="quote-meta">
        @if($quote->number)
        {{ __('app.quote_pdf_number') }}: <strong>{{ $quote->number }}</strong> &nbsp;|&nbsp;
        @else
        <strong>{{ __('app.quote_pdf_draft') }}</strong> &nbsp;|&nbsp;
        @endif
        {{ __('app.quote_pdf_status') }}: {{ $quote->status->label() }}
    </div>

    {{-- Supplier + Customer --}}
    <table style="width:100%; margin-bottom:20px;">
        <tr>
            <td style="width:50%; vertical-align:top; padding-right:20px;">
                <strong>{{ __('app.quote_pdf_supplier') }}</strong><br>
                <strong style="font-size:14px;">{{ $tenant->name }}</strong><br>
                @if($tenant->address_line)
                    {{ $tenant->address_line }}<br>
                @endif
                @if($tenant->city || $tenant->postal_code)
                    {{ implode(' ', array_filter([$tenant->postal_code, $tenant->city])) }}<br>
                @endif
                @if($tenant->country)
                    {{ $tenant->country }}<br>
                @endif
                @if($tenant->ico)
                    {{ __('app.quote_pdf_ico') }}: {{ $tenant->ico }}<br>
                @endif
                @if($tenant->dic)
                    {{ __('app.quote_pdf_dic') }}: {{ $tenant->dic }}<br>
                @endif
            </td>
            <td style="width:50%; vertical-align:top;">
                <strong>{{ __('app.quote_pdf_customer') }}</strong><br>
                <strong>{{ $quote->client?->name ?? $quote->customer_name }}</strong><br>
                @php($street = $quote->client?->street ?? $quote->customer_street)
                @php($city = $quote->client?->city ?? $quote->customer_city)
                @php($postalCode = $quote->client?->postal_code ?? $quote->customer_postal_code)
                @if($street)
                    {{ $street }}<br>
                @endif
                @if($city || $postalCode)
                    {{ implode(' ', array_filter([$postalCode, $city])) }}<br>
                @endif
                @if($quote->cleaningObject)
                    <br><strong>{{ __('app.quote_pdf_object') }}:</strong> {{ $quote->cleaningObject->name }}<br>
                @endif
            </td>
        </tr>
    </table>

    {{-- Dates --}}
    <table style="width:100%; margin-bottom:20px; border-collapse:collapse;">
        <tr>
            <td style="padding:4px 8px 4px 0; white-space:nowrap;">
                <strong>{{ __('app.quote_pdf_issue_date') }}:</strong>
            </td>
            <td style="padding:4px 0;">
                {{ $quote->issue_date ? $quote->issue_date->format('d.m.Y') : '—' }}
            </td>
            <td style="padding:4px 20px;">
                <strong>{{ __('app.quote_pdf_valid_until') }}:</strong>
            </td>
            <td style="padding:4px 0;">
                {{ $quote->valid_until ? $quote->valid_until->format('d.m.Y') : '—' }}
            </td>
        </tr>
    </table>

    {{-- Items --}}
    @php($hasDiscount = $quote->items->contains(fn ($item) => (float) $item->discount_percent > 0))
    <table style="width:100%; border-collapse:collapse; margin-bottom:16px;">
        <thead>
            <tr style="background:#f5f5f5;">
                <th style="text-align:left; padding:6px 8px; border-bottom:1px solid #ddd;">{{ __('app.quote_pdf_item_description') }}</th>
                <th style="text-align:right; padding:6px 8px; border-bottom:1px solid #ddd; white-space:nowrap;">{{ __('app.quote_pdf_item_quantity') }}</th>
                <th style="text-align:left; padding:6px 8px; border-bottom:1px solid #ddd;">{{ __('app.quote_pdf_item_unit') }}</th>
                <th style="text-align:right; padding:6px 8px; border-bottom:1px solid #ddd; white-space:nowrap;">{{ __('app.quote_pdf_item_unit_price') }}</th>
                @if($hasDiscount)
                <th style="text-align:right; padding:6px 8px; border-bottom:1px solid #ddd; white-space:nowrap;">{{ __('app.quote_pdf_discount') }}</th>
                @endif
                @if($quote->is_vat_payer)
                <th style="text-align:right; padding:6px 8px; border-bottom:1px solid #ddd; white-space:nowrap;">{{ __('app.quote_pdf_vat_rate') }}</th>
                @endif
                <th style="text-align:right; padding:6px 8px; border-bottom:1px solid #ddd; white-space:nowrap;">{{ __('app.quote_pdf_item_total') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach($quote->items as $item)
            <tr>
                <td style="padding:6px 8px; border-bottom:1px solid #eee;">
                    {{ $item->description }}
                    @if($item->frequency)
                    <br><span style="font-size:10px; color:#777;">{{ $item->frequency->label() }}</span>
                    @endif
                    @if($item->note)
                    <br><span style="font-size:10px; color:#999;">{{ $item->note }}</span>
                    @endif
                </td>
                <td style="text-align:right; padding:6px 8px; border-bottom:1px solid #eee;">{{ number_format((float) $item->quantity, 2, ',', ' ') }}</td>
                <td style="padding:6px 8px; border-bottom:1px solid #eee;">{{ $item->unit ?? '' }}</td>
                <td style="text-align:right; padding:6px 8px; border-bottom:1px solid #eee;">{{ number_format((float) $item->unit_price, 2, ',', ' ') }} {{ $quote->currency->value }}</td>
                @if($hasDiscount)
                <td style="text-align:right; padding:6px 8px; border-bottom:1px solid #eee;">{{ (float) $item->discount_percent > 0 ? number_format((float) $item->discount_percent, 0) . ' %' : '—' }}</td>
                @endif
                @if($quote->is_vat_payer)
                <td style="text-align:right; padding:6px 8px; border-bottom:1px solid #eee;">{{ number_format((float) $item->vat_rate, 0) }} %</td>
                @endif
                <td style="text-align:right; padding:6px 8px; border-bottom:1px solid #eee;">{{ number_format((float) $item->line_base, 2, ',', ' ') }} {{ $quote->currency->value }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    {{-- Totals --}}
    <table style="width:100%; border-collapse:collapse; margin-bottom:16px;">
        <tr>
            <td style="width:60%;"></td>
            <td style="width:40%;">
                <table style="width:100%; border-collapse:collapse;">
                    @if($quote->is_vat_payer && !empty($quote->vat_breakdown))
                    <tr style="background:#f9f9f9;">
                        <td style="padding:3px 8px; font-size:11px; color:#666;">{{ __('app.invoice_vat_recap_title') }}</td>
                        <td style="padding:3px 8px; font-size:11px; color:#666; text-align:center;">{{ __('app.invoice_vat_recap_rate') }}</td>
                        <td style="padding:3px 8px; font-size:11px; color:#666; text-align:right;">{{ __('app.invoice_vat_recap_base') }}</td>
                        <td style="padding:3px 8px; font-size:11px; color:#666; text-align:right;">{{ __('app.invoice_vat_recap_vat') }}</td>
                        <td style="padding:3px 8px; font-size:11px; color:#666; text-align:right;">{{ __('app.invoice_vat_recap_total') }}</td>
                    </tr>
                    @foreach($quote->vat_breakdown as $line)
                    <tr>
                        <td style="padding:3px 8px; font-size:11px;"></td>
                        <td style="padding:3px 8px; font-size:11px; text-align:center;">{{ number_format((float) $line['rate'], 0) }} %</td>
                        <td style="padding:3px 8px; font-size:11px; text-align:right;">{{ number_format((float) $line['base'], 2, ',', ' ') }} {{ $quote->currency->value }}</td>
                        <td style="padding:3px 8px; font-size:11px; text-align:right;">{{ number_format((float) $line['vat'], 2, ',', ' ') }} {{ $quote->currency->value }}</td>
                        <td style="padding:3px 8px; font-size:11px; text-align:right;">{{ number_format((float) $line['total'], 2, ',', ' ') }} {{ $quote->currency->value }}</td>
                    </tr>
                    @endforeach
                    <tr><td colspan="5" style="border-bottom:1px solid #eee;"></td></tr>
                    @endif

                    <tr>
                        <td colspan="{{ $quote->is_vat_payer ? 4 : 1 }}" style="padding:4px 8px;">{{ __('app.invoice_pdf_subtotal') }}</td>
                        <td style="text-align:right; padding:4px 8px;">{{ number_format((float) $quote->subtotal, 2, ',', ' ') }} {{ $quote->currency->value }}</td>
                    </tr>
                    @if($quote->is_vat_payer)
                    <tr>
                        <td colspan="4" style="padding:4px 8px;">{{ __('app.invoice_pdf_vat_recap') }}</td>
                        <td style="text-align:right; padding:4px 8px;">{{ number_format((float) $quote->vat_amount, 2, ',', ' ') }} {{ $quote->currency->value }}</td>
                    </tr>
                    @endif
                    <tr style="font-weight:bold; font-size:14px; border-top:2px solid #333;">
                        <td colspan="{{ $quote->is_vat_payer ? 4 : 1 }}" style="padding:6px 8px;">{{ __('app.invoice_pdf_total') }}</td>
                        <td style="text-align:right; padding:6px 8px;">{{ number_format((float) $quote->total, 2, ',', ' ') }} {{ $quote->currency->value }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    @if(!$quote->is_vat_payer)
    <p style="font-size:11px; color:#555;">{{ __('app.invoice_pdf_non_vat_payer_clause') }}</p>
    @endif

    @if($quote->note)
    <p style="font-size:11px; color:#555; margin-top:12px;">
        <strong>{{ __('app.quote_pdf_note') }}:</strong> {{ $quote->note }}
    </p>
    @endif
</body>
</html>
