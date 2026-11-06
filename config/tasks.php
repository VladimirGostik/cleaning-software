<?php

declare(strict_types=1);

$office = [
    'doc' => 'application/msword',
    'xls' => 'application/vnd.ms-excel',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
];

return [
    'board' => [
        'column_limit' => 50,
    ],

    'attachments' => [
        'disk' => env('DOCUMENTS_DISK', 'local'),
        'max_size_kb' => 10240,
        'max_count' => 20,

        'types' => [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/heic' => 'heic',
            'image/heif' => 'heif',
            'application/pdf' => 'pdf',
            $office['doc'] => 'doc',
            $office['xls'] => 'xls',
            $office['docx'] => 'docx',
            $office['xlsx'] => 'xlsx',
        ],

        'zip_mimes' => ['application/zip'],
        'zip_extensions' => ['docx' => $office['docx'], 'xlsx' => $office['xlsx']],

        'ole_mimes' => ['application/x-ole-storage', 'application/CDFV2', 'application/vnd.ms-office'],
        'ole_extensions' => ['doc' => $office['doc'], 'xls' => $office['xls']],

        'inline_mimes' => ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'],
        'previewable_mimes' => ['image/jpeg', 'image/png', 'image/webp'],

        'extensions' => ['jpg', 'jpeg', 'png', 'webp', 'heic', 'heif', 'pdf', 'doc', 'docx', 'xls', 'xlsx'],
    ],
];
