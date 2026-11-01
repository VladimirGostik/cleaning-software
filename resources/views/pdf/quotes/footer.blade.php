@include('pdf.partials.kegos-footer', [
    'entity' => [
        'name' => $tenant->name,
        'address' => implode(', ', array_filter([
            $tenant->address_line,
            implode(' ', array_filter([$tenant->postal_code, $tenant->city])),
        ])),
        'ico' => $tenant->ico,
        'dic' => $tenant->dic,
        'registration' => $tenant->registration_info,
        'phone' => $tenant->contact_phone,
        'email' => $tenant->contact_email,
    ],
    'preview' => $preview ?? false,
])
