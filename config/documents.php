<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Business document storage
    |--------------------------------------------------------------------------
    |
    | Private disk for customer-facing business documents (quotes, and in
    | Phase 2: photos, contract attachments, complaints). Never "public" —
    | these carry customer pricing/addresses and must stay policy-gated.
    | Production sets MEDIA_DISK=s3.
    |
    */

    'disk' => env('MEDIA_DISK', 'local'),

    'max_size_kb' => 10240,

    'allowed_mimes' => [
        'application/pdf',
        'image/jpeg',
        'image/png',
        'image/webp',
    ],

];
