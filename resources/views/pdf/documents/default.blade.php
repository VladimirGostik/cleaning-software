<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8" />
    <title>{{ $title }}</title>
    <style>
        {!! file_get_contents(resource_path('css/document-body.css')) !!}
        body { margin: 0; padding: 0; }
    </style>
</head>
<body>
    <div class="document-body">{!! $body !!}</div>
</body>
</html>
