@include('pdf.partials.kegos-footer', [
    'entity' => [
        'name' => $invoice->supplier_name,
        'address' => implode(', ', array_filter([
            $invoice->supplier_address_line,
            implode(' ', array_filter([$invoice->supplier_postal_code, $invoice->supplier_city])),
        ])),
        'ico' => $invoice->supplier_ico,
        'dic' => $invoice->supplier_dic,
        'registration' => $invoice->supplier_registration_info,
        'phone' => $invoice->supplier_contact_phone,
        'email' => $invoice->supplier_contact_email,
    ],
    'issuedBy' => $invoice->issued_by_name,
    'preview' => $preview ?? false,
])
