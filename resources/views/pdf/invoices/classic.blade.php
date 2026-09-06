<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>{{ __('app.invoice_pdf_title') }} {{ $invoice->number ?? __('app.invoice_pdf_draft') }}</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 12px; color: #222; margin: 0; padding: 30px; }
        h1 { font-size: 22px; color: #1a1a1a; margin-bottom: 4px; }
        .invoice-meta { color: #555; font-size: 11px; margin-bottom: 24px; }
        .section-title { font-size: 11px; font-weight: bold; color: #888; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 6px; }
        table { width: 100%; }
    </style>
</head>
<body>
    {{-- Title --}}
    <h1>{{ __('app.invoice_pdf_title') }}</h1>
    <div class="invoice-meta">
        @if($invoice->number)
        {{ __('app.invoice_pdf_number') }}: <strong>{{ $invoice->number }}</strong> &nbsp;|&nbsp;
        @else
        <strong>{{ __('app.invoice_pdf_draft') }}</strong> &nbsp;|&nbsp;
        @endif
        {{ __('app.invoice_pdf_status') }}: {{ $invoice->status->label() }}
    </div>

    {{-- Supplier + Customer --}}
    @include('pdf.invoices.partials.header')

    {{-- Dates --}}
    @include('pdf.invoices.partials.dates')

    {{-- Items --}}
    @include('pdf.invoices.partials.items-table')

    {{-- Totals --}}
    @include('pdf.invoices.partials.totals')

    {{-- Note --}}
    @if($invoice->note)
    <p style="font-size:11px; color:#555; margin-top:12px;">
        <strong>{{ __('app.invoice_pdf_note') }}:</strong> {{ $invoice->note }}
    </p>
    @endif

    {{-- Footer --}}
    @include('pdf.invoices.partials.footer')
</body>
</html>
