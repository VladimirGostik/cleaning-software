<?php

declare(strict_types=1);

return [
    'document' => [
        'disk' => env('DOCUMENTS_DISK', 'local'),
        'max_size_kb' => 10240,
        'allowed_mimes' => ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'],
    ],

    'default_validity_days' => 30,

    'expiring_notice_days' => [7, 3, 1],
];
