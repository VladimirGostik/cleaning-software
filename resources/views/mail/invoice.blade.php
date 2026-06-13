<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>{{ __('app.invoices.mail.subject', ['number' => $invoice->number ?? __('app.invoices.pdf.draft')]) }}</title>
    <style>
        body { font-family: Arial, Helvetica, sans-serif; font-size: 14px; color: #333; background: #f9f9f9; margin: 0; padding: 0; }
        .wrapper { max-width: 600px; margin: 32px auto; background: #fff; border-radius: 8px; overflow: hidden; border: 1px solid #e5e5e5; }
        .header { background: #1e40af; color: #fff; padding: 24px 32px; }
        .header h1 { margin: 0; font-size: 20px; }
        .body { padding: 28px 32px; }
        .footer { background: #f5f5f5; padding: 16px 32px; font-size: 11px; color: #888; }
        .amount { font-size: 22px; font-weight: bold; color: #1e40af; margin: 12px 0; }
        .detail-row { display: flex; gap: 8px; margin-bottom: 6px; }
        .detail-label { color: #888; min-width: 140px; }
    </style>
</head>
<body>
<div class="wrapper">
    <div class="header">
        <h1>{{ __('app.invoices.mail.heading') }}</h1>
    </div>
    <div class="body">
        <p>{{ __('app.invoices.mail.greeting', ['name' => $invoice->customer_name]) }}</p>
        <p>{{ __('app.invoices.mail.body') }}</p>

        <div class="amount">{{ number_format((float) $invoice->total, 2, ',', ' ') }} €</div>

        <table style="width:100%; margin:16px 0; border-collapse:collapse;">
            @if($invoice->number)
            <tr>
                <td style="color:#888; padding:4px 0; width:140px;">{{ __('app.invoices.pdf.number') }}:</td>
                <td style="padding:4px 0;"><strong>{{ $invoice->number }}</strong></td>
            </tr>
            @endif
            @if($invoice->due_date)
            <tr>
                <td style="color:#888; padding:4px 0;">{{ __('app.invoices.pdf.due_date') }}:</td>
                <td style="padding:4px 0;">{{ $invoice->due_date->format('d.m.Y') }}</td>
            </tr>
            @endif
            @if($invoice->supplier_iban)
            <tr>
                <td style="color:#888; padding:4px 0;">{{ __('app.invoices.pdf.iban') }}:</td>
                <td style="padding:4px 0;">{{ $invoice->supplier_iban }}</td>
            </tr>
            @endif
            @if($invoice->variable_symbol)
            <tr>
                <td style="color:#888; padding:4px 0;">{{ __('app.invoices.pdf.variable_symbol') }}:</td>
                <td style="padding:4px 0;">{{ $invoice->variable_symbol }}</td>
            </tr>
            @endif
        </table>

        <p style="font-size:12px; color:#888;">{{ __('app.invoices.mail.attachment_note') }}</p>
    </div>
    <div class="footer">
        {{ $invoice->supplier_name }}
        @if($invoice->supplier_contact_email)
        &nbsp;|&nbsp; {{ $invoice->supplier_contact_email }}
        @endif
    </div>
</div>
</body>
</html>
