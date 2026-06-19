{{-- Dates block: issue_date, delivery_date (dátum dodania), due_date, payment_type --}}
<table style="width:100%; margin-bottom:20px; border-collapse:collapse;">
    <tr>
        <td style="padding:4px 8px 4px 0; white-space:nowrap;">
            <strong>{{ __('app.invoices.pdf.issue_date') }}:</strong>
        </td>
        <td style="padding:4px 0;">
            {{ $invoice->issue_date ? $invoice->issue_date->format('d.m.Y') : '—' }}
        </td>
        <td style="padding:4px 20px;">
            <strong>{{ __('app.invoices.pdf.delivery_date') }}:</strong>
        </td>
        <td style="padding:4px 0;">
            {{ $invoice->delivery_date ? $invoice->delivery_date->format('d.m.Y') : '—' }}
        </td>
        <td style="padding:4px 20px;">
            <strong>{{ __('app.invoices.pdf.due_date') }}:</strong>
        </td>
        <td style="padding:4px 0;">
            {{ $invoice->due_date ? $invoice->due_date->format('d.m.Y') : '—' }}
        </td>
    </tr>
    @if($invoice->period_from || $invoice->period_to)
    <tr>
        <td style="padding:4px 8px 4px 0;" colspan="2">
            <strong>{{ __('app.invoices.pdf.period') }}:</strong>
            {{ $invoice->period_from ? $invoice->period_from->format('d.m.Y') : '—' }}
            –
            {{ $invoice->period_to ? $invoice->period_to->format('d.m.Y') : '—' }}
        </td>
    </tr>
    @endif
    @if($invoice->payment_type)
    <tr>
        <td style="padding:4px 8px 4px 0; white-space:nowrap;">
            <strong>{{ __('app.invoices.pdf.payment_type') }}:</strong>
        </td>
        <td style="padding:4px 0;" colspan="5">
            {{ $invoice->payment_type->label() }}
        </td>
    </tr>
    @endif
</table>
