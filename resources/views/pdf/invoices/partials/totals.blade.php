{{-- Totals block with VAT recapitulation, deposit, rounding, and balance due --}}
@php($currency = $invoice->currency?->value ?? 'EUR')
<table style="width:100%; border-collapse:collapse; margin-bottom:16px;">
    <tr>
        <td style="width:60%;"></td>
        <td style="width:40%;">
            <table style="width:100%; border-collapse:collapse;">
                @if($invoice->is_vat_payer && !empty($invoice->vat_breakdown))
                {{-- VAT recapitulation table --}}
                <tr style="background:#f9f9f9;">
                    <td style="padding:3px 8px; font-size:11px; color:#666;">{{ __('app.invoice_vat_recap_title') }}</td>
                    <td style="padding:3px 8px; font-size:11px; color:#666; text-align:center;">{{ __('app.invoice_vat_recap_rate') }}</td>
                    <td style="padding:3px 8px; font-size:11px; color:#666; text-align:right;">{{ __('app.invoice_vat_recap_base') }}</td>
                    <td style="padding:3px 8px; font-size:11px; color:#666; text-align:right;">{{ __('app.invoice_vat_recap_vat') }}</td>
                    <td style="padding:3px 8px; font-size:11px; color:#666; text-align:right;">{{ __('app.invoice_vat_recap_total') }}</td>
                </tr>
                @foreach($invoice->vat_breakdown as $line)
                <tr>
                    <td style="padding:3px 8px; font-size:11px;"></td>
                    <td style="padding:3px 8px; font-size:11px; text-align:center;">{{ number_format((float) $line['rate'], 0) }} %</td>
                    <td style="padding:3px 8px; font-size:11px; text-align:right;">{{ number_format((float) $line['base'], 2, ',', ' ') }} {{ $currency }}</td>
                    <td style="padding:3px 8px; font-size:11px; text-align:right;">{{ number_format((float) $line['vat'], 2, ',', ' ') }} {{ $currency }}</td>
                    <td style="padding:3px 8px; font-size:11px; text-align:right;">{{ number_format((float) $line['total'], 2, ',', ' ') }} {{ $currency }}</td>
                </tr>
                @endforeach
                <tr><td colspan="5" style="border-bottom:1px solid #eee;"></td></tr>
                @endif

                <tr>
                    <td colspan="{{ $invoice->is_vat_payer ? 4 : 1 }}" style="padding:4px 8px;">{{ __('app.invoice_pdf_subtotal') }}</td>
                    <td style="text-align:right; padding:4px 8px;">{{ number_format((float) $invoice->subtotal, 2, ',', ' ') }} {{ $currency }}</td>
                </tr>
                @if($invoice->is_vat_payer)
                <tr>
                    <td colspan="4" style="padding:4px 8px;">{{ __('app.invoice_pdf_vat_recap') }}</td>
                    <td style="text-align:right; padding:4px 8px;">{{ number_format((float) $invoice->vat_amount, 2, ',', ' ') }} {{ $currency }}</td>
                </tr>
                @endif
                @if((float) $invoice->rounding_amount !== 0.0)
                <tr>
                    <td colspan="{{ $invoice->is_vat_payer ? 4 : 1 }}" style="padding:4px 8px; color:#666; font-size:11px;">{{ __('app.invoice_pdf_rounding') }}</td>
                    <td style="text-align:right; padding:4px 8px; color:#666; font-size:11px;">{{ number_format((float) $invoice->rounding_amount, 2, ',', ' ') }} {{ $currency }}</td>
                </tr>
                @endif
                <tr style="font-weight:bold; font-size:14px; border-top:2px solid #333;">
                    <td colspan="{{ $invoice->is_vat_payer ? 4 : 1 }}" style="padding:6px 8px;">{{ __('app.invoice_pdf_total') }}</td>
                    <td style="text-align:right; padding:6px 8px;">{{ number_format((float) $invoice->total, 2, ',', ' ') }} {{ $currency }}</td>
                </tr>
                @if((float) $invoice->deposit > 0)
                <tr>
                    <td colspan="{{ $invoice->is_vat_payer ? 4 : 1 }}" style="padding:4px 8px;">{{ __('app.invoice_pdf_deposit') }}</td>
                    <td style="text-align:right; padding:4px 8px;">{{ number_format((float) $invoice->deposit, 2, ',', ' ') }} {{ $currency }}</td>
                </tr>
                <tr style="font-weight:bold; border-top:1px solid #aaa;">
                    <td colspan="{{ $invoice->is_vat_payer ? 4 : 1 }}" style="padding:6px 8px;">{{ __('app.invoice_pdf_balance_due') }}</td>
                    <td style="text-align:right; padding:6px 8px;">{{ number_format((float) $invoice->balance_due, 2, ',', ' ') }} {{ $currency }}</td>
                </tr>
                @endif
            </table>
        </td>
    </tr>
</table>

@if(!$invoice->is_vat_payer)
<p style="font-size:11px; color:#555;">{{ __('app.invoice_pdf_non_vat_payer_clause') }}</p>
@endif
