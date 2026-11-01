<?php

declare(strict_types=1);

return [
    'receipt' => [
        'disk' => env('DOCUMENTS_DISK', 'local'),
        'max_size_kb' => 10240,
        'allowed_mimes' => ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'],
    ],

    'csv' => [
        'max_size_kb' => 1024,
    ],
];
