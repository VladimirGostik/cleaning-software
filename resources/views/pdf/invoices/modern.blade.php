<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8" />
    <title>{{ __('app.invoice_pdf_title') }} {{ $invoice->number ?? __('app.invoice_pdf_draft') }}</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 12px; color: #1e293b; margin: 0; padding: 0; }
        .header-bar { background: #1e40af; color: #fff; padding: 28px 32px 20px; }
        .header-bar h1 { margin: 0 0 4px; font-size: 24px; font-weight: 700; }
        .header-bar .meta { font-size: 11px; opacity: 0.8; }
        .content { padding: 28px 32px; }
        .label { font-size: 10px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.6px; margin-bottom: 2px; }
    </style>
</head>
<body>
    <div class="header-bar">
        <h1>{{ __('app.invoice_pdf_title') }}</h1>
        <div class="meta">
            @if($invoice->number)
            {{ __('app.invoice_pdf_number') }}: <strong>{{ $invoice->number }}</strong> &nbsp;|&nbsp;
            @else
            <strong>{{ __('app.invoice_pdf_draft') }}</strong> &nbsp;|&nbsp;
            @endif
            {{ __('app.invoice_pdf_status') }}: {{ $invoice->status->label() }}
        </div>
    </div>

    <div class="content">
        @include('pdf.invoices.partials.header')
        @include('pdf.invoices.partials.dates')
        @include('pdf.invoices.partials.items-table')
        @include('pdf.invoices.partials.totals')

        @if($invoice->note)
        <p style="font-size:11px; color:#64748b; margin-top:12px;">
            <strong>{{ __('app.invoice_pdf_note') }}:</strong> {{ $invoice->note }}
        </p>
        @endif

        @include('pdf.invoices.partials.footer')
    </div>
</body>
</html>
