<?php

declare(strict_types=1);

return [
    'disk' => env('DOCUMENTS_DISK', 'local'),

    'employee_files' => [
        'max_size_kb' => 10240,
        'allowed_mimes' => ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'],
    ],

    'locale' => env('DOCUMENTS_LOCALE', 'sk'),

    'show_brand_line' => false,

    'max_body_length' => 200000,
];
