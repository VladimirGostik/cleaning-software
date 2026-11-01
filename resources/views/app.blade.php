<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="app-theme">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title inertia>{{ config('app.name', 'Kegos Clean') }}</title>

        <link rel="preload" as="font" type="font/woff2" crossorigin href="{{ Vite::asset('resources/fonts/geist/Geist[wght].woff2') }}">
        <link rel="icon" href="/favicon.ico" sizes="48x48">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">
        <link rel="manifest" href="/site.webmanifest">
        <meta name="theme-color" content="#0E7C5F">

        @vite(['resources/css/app.css', 'resources/js/app.ts'])
        @inertiaHead
    </head>
    <body class="font-sans antialiased min-h-screen bg-base-100 text-base-content">
        @inertia
    </body>
</html>
