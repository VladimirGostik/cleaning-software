<?php

declare(strict_types=1);

$office = [
    'doc' => 'application/msword',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'odt' => 'application/vnd.oasis.opendocument.text',
];

return [
    'disk' => env('DOCUMENTS_DISK', 'local'),

    'employee_files' => [
        'max_size_kb' => 10240,
        'allowed_mimes' => ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'],
    ],

    'contract_files' => [
        'max_size_kb' => 10240,
        'max_attachments' => 20,

        'types' => [
            'application/pdf' => 'pdf',
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            $office['doc'] => 'doc',
            $office['docx'] => 'docx',
            $office['odt'] => 'odt',
        ],

        'zip_mimes' => ['application/zip'],
        'zip_extensions' => ['docx' => $office['docx'], 'odt' => $office['odt']],

        'ole_mimes' => ['application/x-ole-storage', 'application/CDFV2', 'application/vnd.ms-office'],
        'ole_extensions' => ['doc' => $office['doc']],

        'inline_mimes' => ['application/pdf', 'image/jpeg', 'image/png'],

        'extensions' => ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx', 'odt'],
    ],

    'locale' => env('DOCUMENTS_LOCALE', 'sk'),

    'show_brand_line' => false,

    'max_body_length' => 200000,
];
