{{-- Totals block with conditional VAT rows --}}
<table style="width:100%; border-collapse:collapse; margin-bottom:16px;">
    <tr>
        <td style="width:60%;"></td>
        <td style="width:40%;">
            <table style="width:100%; border-collapse:collapse;">
                <tr>
                    <td style="padding:4px 8px;">{{ __('app.invoices.pdf.subtotal') }}</td>
                    <td style="text-align:right; padding:4px 8px;">{{ number_format((float) $invoice->subtotal, 2, ',', ' ') }} €</td>
                </tr>
                @if($invoice->is_vat_payer)
                <tr>
                    <td style="padding:4px 8px;">{{ __('app.invoices.pdf.vat_rate') }} {{ $invoice->vat_rate ? number_format((float) $invoice->vat_rate, 0) : 0 }} %</td>
                    <td style="text-align:right; padding:4px 8px;">{{ number_format((float) $invoice->vat_amount, 2, ',', ' ') }} €</td>
                </tr>
                @endif
                <tr style="font-weight:bold; font-size:14px; border-top:2px solid #333;">
                    <td style="padding:6px 8px;">{{ __('app.invoices.pdf.total') }}</td>
                    <td style="text-align:right; padding:6px 8px;">{{ number_format((float) $invoice->total, 2, ',', ' ') }} €</td>
                </tr>
            </table>
        </td>
    </tr>
</table>

@if(!$invoice->is_vat_payer)
<p style="font-size:11px; color:#555;">{{ __('app.invoices.pdf.non_vat_payer_clause') }}</p>
@endif
