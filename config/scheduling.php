<?php

declare(strict_types=1);

return [
    'horizon_days' => (int) env('SCHEDULING_HORIZON_DAYS', 30),
    'timezone' => env('SCHEDULING_TIMEZONE', 'Europe/Bratislava'),
    'my_jobs' => [
        'max_range_days' => 62,
    ],
    'work_records' => [
        'backdate_days' => 7,
        'future_tolerance_minutes' => 5,
        'photos' => [
            'disk' => env('DOCUMENTS_DISK', 'local'),
            'max_size_kb' => 10240,
            'max_count' => 5,
            'upload_window_days' => 7,
            'types' => [
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/webp' => 'webp',
                'image/heic' => 'heic',
                'image/heif' => 'heif',
            ],
            'inline_mimes' => ['image/jpeg', 'image/png', 'image/webp'],
            'extensions' => ['jpg', 'jpeg', 'png', 'webp', 'heic', 'heif'],
            'zip_mimes' => [],
            'ole_mimes' => [],
        ],
    ],
];
