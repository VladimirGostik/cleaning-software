<?php

declare(strict_types=1);

return [
    'vat_rates' => [23, 19, 5, 0],

    'default_number_format' => 'FA-{YYYY}-{XXXX}',

    'default_due_days' => 14,

    'signature' => [
        'disk' => env('DOCUMENTS_DISK', 'local'),
        'max_size_kb' => 1024,
        'allowed_mimes' => ['image/png', 'image/jpeg', 'image/webp'],
    ],
];
