{{-- Invoice items table --}}
@php
    $hasDiscount = $invoice->items->contains(fn ($item) => (float) $item->discount_percent > 0);
@endphp
<table style="width:100%; border-collapse:collapse; margin-bottom:16px;">
    <thead>
        <tr style="background:#f5f5f5;">
            <th style="text-align:left; padding:6px 8px; border-bottom:1px solid #ddd;">{{ __('app.invoices.pdf.item_description') }}</th>
            <th style="text-align:right; padding:6px 8px; border-bottom:1px solid #ddd; white-space:nowrap;">{{ __('app.invoices.pdf.item_quantity') }}</th>
            <th style="text-align:left; padding:6px 8px; border-bottom:1px solid #ddd;">{{ __('app.invoices.pdf.item_unit') }}</th>
            <th style="text-align:right; padding:6px 8px; border-bottom:1px solid #ddd; white-space:nowrap;">{{ __('app.invoices.pdf.item_unit_price') }}</th>
            @if($hasDiscount)
            <th style="text-align:right; padding:6px 8px; border-bottom:1px solid #ddd; white-space:nowrap;">{{ __('app.invoices.pdf.discount') }}</th>
            @endif
            @if($invoice->is_vat_payer)
            <th style="text-align:right; padding:6px 8px; border-bottom:1px solid #ddd; white-space:nowrap;">{{ __('app.invoices.pdf.vat_rate') }}</th>
            @endif
            <th style="text-align:right; padding:6px 8px; border-bottom:1px solid #ddd; white-space:nowrap;">{{ __('app.invoices.pdf.item_total') }}</th>
        </tr>
    </thead>
    <tbody>
        @foreach($invoice->items as $item)
        <tr>
            <td style="padding:6px 8px; border-bottom:1px solid #eee;">{{ $item->description }}</td>
            <td style="text-align:right; padding:6px 8px; border-bottom:1px solid #eee;">{{ number_format((float) $item->quantity, 2, ',', ' ') }}</td>
            <td style="padding:6px 8px; border-bottom:1px solid #eee;">{{ $item->unit ?? '' }}</td>
            <td style="text-align:right; padding:6px 8px; border-bottom:1px solid #eee;">{{ number_format((float) $item->unit_price, 2, ',', ' ') }} €</td>
            @if($hasDiscount)
            <td style="text-align:right; padding:6px 8px; border-bottom:1px solid #eee;">{{ (float) $item->discount_percent > 0 ? number_format((float) $item->discount_percent, 0) . ' %' : '—' }}</td>
            @endif
            @if($invoice->is_vat_payer)
            <td style="text-align:right; padding:6px 8px; border-bottom:1px solid #eee;">{{ number_format((float) $item->vat_rate, 0) }} %</td>
            @endif
            <td style="text-align:right; padding:6px 8px; border-bottom:1px solid #eee;">{{ number_format((float) $item->line_base, 2, ',', ' ') }} €</td>
        </tr>
        @endforeach
    </tbody>
</table>
