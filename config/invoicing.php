<?php

declare(strict_types=1);

return [
    'vat_rates' => [23, 19, 5, 0],

    'default_number_format' => 'FA-{YYYY}-{XXXX}',

    'default_due_days' => 14,

    'issue_reminder' => [
        'day' => 30,
    ],

    'signature' => [
        'disk' => env('DOCUMENTS_DISK', 'local'),
        'max_size_kb' => 1024,
        'allowed_mimes' => ['image/png', 'image/jpeg', 'image/webp'],
    ],

    'bulk' => [
        'max_ids' => 100,
        'pdf_max_ids' => 25,
        'print_all_max' => 200,
        'merge_timeout_seconds' => 60,
        'qpdf_binary' => env('QPDF_BINARY', 'qpdf'),
    ],

    'payment_import' => [
        'max_files' => 12,
        'max_file_kb' => 1024,
        'max_rows' => 500,
        'throttle' => 20,
        'search_throttle' => 120,
    ],
];
