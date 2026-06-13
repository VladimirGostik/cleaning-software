<?php

declare(strict_types=1);

use Spatie\LaravelPdf\Caching\DefaultPdfCache;
use Spatie\LaravelPdf\Encryption\DefaultPdfEncrypter;
use Spatie\LaravelPdf\Jobs\GeneratePdfJob;

return [
    /*
     * The default driver to use for PDF generation.
     * Supported: "browsershot", "cloudflare", "dompdf", "gotenberg", "chrome"
     *
     * "chrome" uses chrome-php/chrome (pure PHP DevTools Protocol) — no Node/Puppeteer needed at runtime.
     * Binary is the Puppeteer-managed Chrome-for-Testing at /usr/local/bin/chromium-real (symlink).
     */
    'driver' => env('LARAVEL_PDF_DRIVER', 'chrome'),

    /*
     * Render caching.
     */
    'cache' => [
        'class' => DefaultPdfCache::class,
        'automatic' => env('LARAVEL_PDF_CACHE_AUTOMATIC', false),
        'store' => env('LARAVEL_PDF_CACHE_STORE'),
        'prefix' => 'laravel-pdf',
        'ttl' => env('LARAVEL_PDF_CACHE_TTL', 60 * 60 * 24),
    ],

    /*
     * Browsershot driver configuration (kept for reference; not the active driver).
     */
    'browsershot' => [
        'node_binary' => env('LARAVEL_PDF_NODE_BINARY'),
        'npm_binary' => env('LARAVEL_PDF_NPM_BINARY'),
        'include_path' => env('LARAVEL_PDF_INCLUDE_PATH'),
        'chrome_path' => env('LARAVEL_PDF_CHROME_PATH', env('CHROMIUM_PATH', '/usr/local/bin/chromium-real')),
        'node_modules_path' => env('LARAVEL_PDF_NODE_MODULES_PATH'),
        'bin_path' => env('LARAVEL_PDF_BIN_PATH'),
        'temp_path' => env('LARAVEL_PDF_TEMP_PATH'),
        'write_options_to_file' => env('LARAVEL_PDF_WRITE_OPTIONS_TO_FILE', true),
        'no_sandbox' => env('LARAVEL_PDF_NO_SANDBOX', true),
    ],

    /*
     * Cloudflare Browser Rendering driver configuration.
     */
    'cloudflare' => [
        'api_token' => env('CLOUDFLARE_API_TOKEN'),
        'account_id' => env('CLOUDFLARE_ACCOUNT_ID'),
    ],

    /*
     * Gotenberg driver configuration.
     */
    'gotenberg' => [
        'url' => env('GOTENBERG_URL', 'http://localhost:3000'),
        'username' => env('GOTENBERG_USERNAME'),
        'password' => env('GOTENBERG_PASSWORD'),
    ],

    /*
     * DOMPDF driver configuration (fallback only).
     */
    'dompdf' => [
        'is_remote_enabled' => env('LARAVEL_PDF_DOMPDF_REMOTE_ENABLED', false),
        'chroot' => env('LARAVEL_PDF_DOMPDF_CHROOT'),
    ],

    /*
     * WeasyPrint driver configuration.
     */
    'weasyprint' => [
        'binary' => env('LARAVEL_PDF_WEASYPRINT_BINARY', 'weasyprint'),
        'timeout' => 10,
    ],

    /*
     * Chrome PHP driver configuration (chrome-php/chrome — pure PHP DevTools Protocol).
     * Binary: Puppeteer Chrome-for-Testing symlinked at /usr/local/bin/chromium-real inside the Sail image.
     * --no-sandbox and --disable-gpu are required in Docker.
     */
    'chrome' => [
        'chrome_binary' => env('LARAVEL_PDF_CHROME_BINARY', env('CHROMIUM_PATH', '/usr/local/bin/chromium-real')),
        'no_sandbox' => env('LARAVEL_PDF_CHROME_NO_SANDBOX', true),
        'startup_timeout' => env('LARAVEL_PDF_CHROME_STARTUP_TIMEOUT', 30),
        'timeout' => env('LARAVEL_PDF_CHROME_TIMEOUT', 30000),
        'operation_timeout' => env('LARAVEL_PDF_CHROME_OPERATION_TIMEOUT', 5000),
        'user_data_dir' => env('LARAVEL_PDF_CHROME_USER_DATA_DIR'),
        'custom_flags' => ['--disable-gpu', '--disable-dev-shm-usage'],
        'env_variables' => [],
    ],

    'job' => GeneratePdfJob::class,

    'encrypter' => DefaultPdfEncrypter::class,
];
