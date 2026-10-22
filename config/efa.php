<?php

declare(strict_types=1);

// Every value below is a regulatory/technical assumption to be confirmed with the accountant before a live driver is enabled.
return [
    'driver' => env('EFA_DRIVER', 'null'),

    'customization_id' => 'urn:cen.eu:en16931:2017#compliant#urn:fdc:peppol.eu:2017:poacc:billing:3.0',

    'profile_id' => 'urn:fdc:peppol.eu:2017:poacc:billing:01:1.0',

    'seller_scheme_id' => '0245',

    'vat_scheme_id' => 'VAT',

    'email_endpoint_scheme_id' => 'EM',

    'participant_schemes' => ['0088', '0192', '0204', '0245', '9925', '9950'],

    'unit_code_map' => [
        'ks' => 'C62',
        'hod' => 'HUR',
        'm2' => 'MTK',
        'm' => 'MTR',
        'mesiac' => 'MON',
        'deň' => 'DAY',
        'bal' => 'PK',
        'l' => 'LTR',
    ],

    'default_unit_code' => 'C62',

    'payment_means_map' => [
        'transfer' => '30',
        'cash' => '10',
        'card' => '48',
        'cod' => '10',
        'other' => 'ZZZ',
    ],

    'vat_exemption_reason_code' => 'VATEX-EU-O',

    'mandatory_from' => '2027-01-01',

    'applies_to_non_vat_payers' => false,
];
