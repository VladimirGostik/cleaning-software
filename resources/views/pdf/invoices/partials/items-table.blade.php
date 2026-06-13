{{-- Invoice items table --}}
<table style="width:100%; border-collapse:collapse; margin-bottom:16px;">
    <thead>
        <tr style="background:#f5f5f5;">
            <th style="text-align:left; padding:6px 8px; border-bottom:1px solid #ddd;">{{ __('app.invoices.pdf.item_description') }}</th>
            <th style="text-align:right; padding:6px 8px; border-bottom:1px solid #ddd; white-space:nowrap;">{{ __('app.invoices.pdf.item_quantity') }}</th>
            <th style="text-align:left; padding:6px 8px; border-bottom:1px solid #ddd;">{{ __('app.invoices.pdf.item_unit') }}</th>
            <th style="text-align:right; padding:6px 8px; border-bottom:1px solid #ddd; white-space:nowrap;">{{ __('app.invoices.pdf.item_unit_price') }}</th>
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
            <td style="text-align:right; padding:6px 8px; border-bottom:1px solid #eee;">{{ number_format((float) $item->total, 2, ',', ' ') }} €</td>
        </tr>
        @endforeach
    </tbody>
</table>
