<?php

declare(strict_types=1);

return [
    'application' => 'CleanMaster',

    'version' => '2.0',

    'encoding' => env('POHODA_ENCODING', 'UTF-8'),

    'max_invoices' => 2000,

    'max_range_days' => 366,

    'namespaces' => [
        'dat' => 'http://www.stormware.cz/schema/version_2/data.xsd',
        'inv' => 'http://www.stormware.cz/schema/version_2/invoice.xsd',
        'typ' => 'http://www.stormware.cz/schema/version_2/type.xsd',
    ],

    'vat_band_map' => [
        23 => 'high',
        19 => 'low',
        5 => 'third',
        0 => 'none',
    ],
];
