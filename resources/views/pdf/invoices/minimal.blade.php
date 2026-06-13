<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8" />
    <title>{{ __('app.invoices.pdf.title') }} {{ $invoice->number ?? __('app.invoices.pdf.draft') }}</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 11px; color: #333; margin: 0; padding: 32px; }
        h1 { font-size: 18px; font-weight: 400; letter-spacing: 2px; text-transform: uppercase; color: #555; border-bottom: 1px solid #ddd; padding-bottom: 8px; margin-bottom: 8px; }
        .meta { font-size: 10px; color: #888; margin-bottom: 24px; }
    </style>
</head>
<body>
    <h1>{{ __('app.invoices.pdf.title') }}</h1>
    <div class="meta">
        @if($invoice->number)
        {{ __('app.invoices.pdf.number') }}: <strong>{{ $invoice->number }}</strong> &nbsp;|&nbsp;
        @else
        <strong>{{ __('app.invoices.pdf.draft') }}</strong> &nbsp;|&nbsp;
        @endif
        {{ __('app.invoices.pdf.status') }}: {{ $invoice->status->label() }}
    </div>

    @include('pdf.invoices.partials.header')
    @include('pdf.invoices.partials.dates')
    @include('pdf.invoices.partials.items-table')
    @include('pdf.invoices.partials.totals')

    @if($invoice->note)
    <p style="font-size:10px; color:#888; margin-top:12px;">
        <strong>{{ __('app.invoices.pdf.note') }}:</strong> {{ $invoice->note }}
    </p>
    @endif

    @include('pdf.invoices.partials.footer')
</body>
</html>
