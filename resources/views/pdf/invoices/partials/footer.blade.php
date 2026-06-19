{{-- Footer: payment info + QR + registration info + optional footer_text --}}
<table style="width:100%; margin-top:20px; border-top:1px solid #ddd; padding-top:12px;">
    <tr>
        <td style="vertical-align:top; width:65%;">
            @if($invoice->supplier_iban)
            <strong>{{ __('app.invoices.pdf.iban') }}:</strong> {{ $invoice->supplier_iban }}<br>
            @endif
            @if($invoice->supplier_swift)
            <strong>{{ __('app.invoices.pdf.swift') }}:</strong> {{ $invoice->supplier_swift }}<br>
            @endif
            @if($invoice->variable_symbol)
            <strong>{{ __('app.invoices.pdf.variable_symbol') }}:</strong> {{ $invoice->variable_symbol }}<br>
            @endif
            @if($invoice->constant_symbol)
            <strong>{{ __('app.invoices.pdf.constant_symbol') }}:</strong> {{ $invoice->constant_symbol }}<br>
            @endif
            @if($invoice->specific_symbol)
            <strong>{{ __('app.invoices.pdf.specific_symbol') }}:</strong> {{ $invoice->specific_symbol }}<br>
            @endif
            @if($invoice->supplier_contact_email || $invoice->supplier_contact_phone)
            <br>
            @if($invoice->supplier_contact_email)
            {{ __('app.invoices.pdf.email') }}: {{ $invoice->supplier_contact_email }}<br>
            @endif
            @if($invoice->supplier_contact_phone)
            {{ __('app.invoices.pdf.phone') }}: {{ $invoice->supplier_contact_phone }}<br>
            @endif
            @endif
            @if($invoice->supplier_registration_info)
            <br><span style="font-size:10px; color:#777;">{{ $invoice->supplier_registration_info }}</span>
            @endif
        </td>
        @if($qrDataUri)
        <td style="vertical-align:top; text-align:right; width:35%;">
            <img src="{{ $qrDataUri }}" width="120" height="120" alt="PAY by square QR" />
            <br><span style="font-size:9px; color:#888;">PAY by square</span>
        </td>
        @endif
    </tr>
</table>
@if($invoice->footer_text)
<div style="margin-top:16px; font-size:11px; color:#555;">{{ $invoice->footer_text }}</div>
@endif
