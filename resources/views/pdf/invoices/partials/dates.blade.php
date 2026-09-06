{{-- Dates block: issue_date, delivery_date (dátum dodania), due_date, payment_type --}}
<table style="width:100%; margin-bottom:20px; border-collapse:collapse;">
    <tr>
        <td style="padding:4px 8px 4px 0; white-space:nowrap;">
            <strong>{{ __('app.invoice_pdf_issue_date') }}:</strong>
        </td>
        <td style="padding:4px 0;">
            {{ $invoice->issue_date ? $invoice->issue_date->format('d.m.Y') : '—' }}
        </td>
        <td style="padding:4px 20px;">
            <strong>{{ __('app.invoice_pdf_delivery_date') }}:</strong>
        </td>
        <td style="padding:4px 0;">
            {{ $invoice->delivery_date ? $invoice->delivery_date->format('d.m.Y') : '—' }}
        </td>
        <td style="padding:4px 20px;">
            <strong>{{ __('app.invoice_pdf_due_date') }}:</strong>
        </td>
        <td style="padding:4px 0;">
            {{ $invoice->due_date ? $invoice->due_date->format('d.m.Y') : '—' }}
        </td>
    </tr>
    @if($invoice->period_from || $invoice->period_to)
    <tr>
        <td style="padding:4px 8px 4px 0;" colspan="2">
            <strong>{{ __('app.invoice_pdf_period') }}:</strong>
            {{ $invoice->period_from ? $invoice->period_from->format('d.m.Y') : '—' }}
            –
            {{ $invoice->period_to ? $invoice->period_to->format('d.m.Y') : '—' }}
        </td>
    </tr>
    @endif
    @if($invoice->payment_type)
    <tr>
        <td style="padding:4px 8px 4px 0; white-space:nowrap;">
            <strong>{{ __('app.invoice_pdf_payment_type') }}:</strong>
        </td>
        <td style="padding:4px 0;" colspan="5">
            {{ $invoice->payment_type->label() }}
        </td>
    </tr>
    @endif
</table>
