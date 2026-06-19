<?php

declare(strict_types=1);

namespace App\Data\Invoices;

use App\Models\Invoice;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class InvoiceSupplierData extends Data
{
    public function __construct(
        public string $name,
        public ?string $ico,
        public ?string $dic,
        public ?string $vat_number,
        public ?string $iban,
        public ?string $swift,
        public ?string $address_line,
        public ?string $city,
        public ?string $postal_code,
        public ?string $country,
        public ?string $contact_email,
        public ?string $contact_phone,
        public ?string $registration_info,
    ) {}

    public static function fromInvoice(Invoice $invoice): self
    {
        return new self(
            name: $invoice->supplier_name,
            ico: $invoice->supplier_ico,
            dic: $invoice->supplier_dic,
            vat_number: $invoice->supplier_vat_number,
            iban: $invoice->supplier_iban,
            swift: $invoice->supplier_swift,
            address_line: $invoice->supplier_address_line,
            city: $invoice->supplier_city,
            postal_code: $invoice->supplier_postal_code,
            country: $invoice->supplier_country,
            contact_email: $invoice->supplier_contact_email,
            contact_phone: $invoice->supplier_contact_phone,
            registration_info: $invoice->supplier_registration_info,
        );
    }
}
