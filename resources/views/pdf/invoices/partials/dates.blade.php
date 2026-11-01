{{-- Dates block: issue_date, delivery_date (dátum dodania), due_date, payment_type --}}
<table style="width:100%; margin-bottom:20px; border-collapse:collapse;">
    <tr>
        <td style="padding:4px 8px 4px 0; white-space:nowrap;">
            <strong>{{ __('app.invoice_pdf_issue_date') }}:</strong>
        </td>
        <td style="padding:4px 0;">
            {{ format_date($invoice->issue_date) }}
        </td>
        <td style="padding:4px 20px;">
            <strong>{{ __('app.invoice_pdf_delivery_date') }}:</strong>
        </td>
        <td style="padding:4px 0;">
            {{ format_date($invoice->delivery_date) }}
        </td>
        <td style="padding:4px 20px;">
            <strong>{{ __('app.invoice_pdf_due_date') }}:</strong>
        </td>
        <td style="padding:4px 0;">
            {{ format_date($invoice->due_date) }}
        </td>
    </tr>
    @if($invoice->period_from || $invoice->period_to)
    <tr>
        <td style="padding:4px 8px 4px 0;" colspan="2">
            <strong>{{ __('app.invoice_pdf_period') }}:</strong>
            {{ format_date($invoice->period_from) }}
            –
            {{ format_date($invoice->period_to) }}
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
