<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>{{ $title }}</title>
    @include('pdf.partials.kegos-base')
    <style>
        {!! file_get_contents(resource_path('css/document-body.css')) !!}
        body { font-size: 10pt; }
        .q-top td { vertical-align: middle; padding: 1mm 0 0; }
        .q-logo svg { display: block; height: 5mm; width: 45.63mm; overflow: visible; }
        .q-doctype { text-align: right; font-size: 8pt; letter-spacing: .22em; color: #5A6661; font-weight: 500; text-transform: uppercase; }
        .q-docno { text-align: right; font-size: 8.5pt; color: #5A6661; margin-top: 1mm; }
    </style>
</head>
<body>
@if($showHeader)
<table class="q-top">
    <tr>
        <td class="q-logo">{!! file_get_contents(resource_path('images/brand/kegos-logo.svg')) !!}</td>
        <td>
            <div class="q-doctype">{{ __('app.quote_pdf_title') }}</div>
            @if(filled($reference))<div class="q-docno">{{ $reference }}</div>@endif
        </td>
    </tr>
</table>
@endif
<div class="document-body document-body--quote">{!! $body !!}</div>
</body>
</html>
