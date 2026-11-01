<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>{{ $mailSubject }}</title>
    <style>
        body { font-family: 'Geist', Arial, Helvetica, sans-serif; font-size: 14px; color: #111C18; background: #F6F7F6; margin: 0; padding: 0; }
        .wrapper { max-width: 600px; margin: 32px auto; background: #fff; border-radius: 16px; overflow: hidden; border: 1px solid #E9ECEA; }
        .header { padding: 24px 32px; border-bottom: 1px solid #E9ECEA; }
        .header h1 { margin: 16px 0 0; font-size: 20px; font-weight: 600; letter-spacing: -0.02em; color: #111C18; }
        .body { padding: 28px 32px; }
        .footer { padding: 16px 32px; border-top: 1px solid #E9ECEA; font-size: 12px; color: #5A6661; }
        .amount { font-size: 22px; font-weight: 600; color: #111C18; margin: 12px 0; }
    </style>
</head>
<body>
<div class="wrapper">
    <div class="header">
        <img src="{{ asset('images/kegos-logo-email.png') }}" alt="KEGOS" width="180" style="display:block; width:180px; height:auto;" />
        <h1>{{ __('app.invoice_mail_heading') }}</h1>
    </div>
    <div class="body">
        @if($bodyText !== null)
        <p>{!! nl2br(e($bodyText)) !!}</p>
        @else
        <p>{{ __('app.invoice_mail_greeting', ['name' => $invoice->customer_name]) }}</p>
        <p>{{ __('app.invoice_mail_body') }}</p>
        @endif

        <div class="amount">{{ $invoice->currency->format((float) $invoice->total) }}</div>

        <table style="width:100%; margin:16px 0; border-collapse:collapse;">
            @if($invoice->number)
            <tr>
                <td style="color:#5A6661; padding:4px 0; width:140px;">{{ __('app.invoice_pdf_number') }}:</td>
                <td style="padding:4px 0;"><strong>{{ $invoice->number }}</strong></td>
            </tr>
            @endif
            @if($invoice->due_date)
            <tr>
                <td style="color:#5A6661; padding:4px 0;">{{ __('app.invoice_pdf_due_date') }}:</td>
                <td style="padding:4px 0;">{{ format_date($invoice->due_date) }}</td>
            </tr>
            @endif
            @if($invoice->supplier_iban)
            <tr>
                <td style="color:#5A6661; padding:4px 0;">{{ __('app.invoice_pdf_iban') }}:</td>
                <td style="padding:4px 0;">{{ $invoice->supplier_iban }}</td>
            </tr>
            @endif
            @if($invoice->variable_symbol)
            <tr>
                <td style="color:#5A6661; padding:4px 0;">{{ __('app.invoice_pdf_variable_symbol') }}:</td>
                <td style="padding:4px 0;">{{ $invoice->variable_symbol }}</td>
            </tr>
            @endif
        </table>

        <p style="font-size:12px; color:#5A6661;">{{ __('app.invoice_mail_attachment_note') }}</p>
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
