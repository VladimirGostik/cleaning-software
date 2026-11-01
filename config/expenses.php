<?php

declare(strict_types=1);

return [
    'receipt' => [
        'disk' => env('DOCUMENTS_DISK', 'local'),
        'max_size_kb' => 10240,
        'allowed_mimes' => ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'],
    ],

    'statement' => [
        'max_size_kb' => 5120,
        'max_rows' => 2000,
        'max_new_rows' => 100,
    ],
];
