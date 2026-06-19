<?php

declare(strict_types=1);

namespace App\Services\Pdf;

use App\Enums\CurrencyEnum;
use App\Enums\InvoiceStatusEnum;
use App\Enums\InvoiceTemplateEnum;
use App\Enums\InvoiceTypeEnum;
use App\Enums\PaymentTypeEnum;
use App\Enums\RoundingModeEnum;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

final readonly class InvoicePreviewData
{
    /** Build an unsaved Invoice model with fake snapshot data for PDF template preview. */
    public static function make(InvoiceTemplateEnum $template): Invoice
    {
        $invoice = (new Invoice)->forceFill([
            'type' => InvoiceTypeEnum::OneOff,
            'status' => InvoiceStatusEnum::Issued,
            'template' => $template,
            'number' => 'FA-2024-0001',
            'variable_symbol' => '20240001',
            'issue_date' => Carbon::now(),
            'delivery_date' => Carbon::now(),
            'due_date' => Carbon::now()->addDays(14),
            'is_vat_payer' => true,
            'vat_rate' => '23.00',
            // item1: qty=2 × 30 −10% → base=54.00 @23%; item2: qty=1 × 40 @19% → base=40.00
            'subtotal' => '94.00',
            'vat_amount' => '20.02',
            'total' => '114.02',
            'deposit' => '20.00',
            'rounding_amount' => '0.00',
            'vat_breakdown' => [
                ['rate' => 23.0, 'base' => 54.0, 'vat' => 12.42, 'total' => 66.42],
                ['rate' => 19.0, 'base' => 40.0, 'vat' => 7.60, 'total' => 47.60],
            ],
            'constant_symbol' => '0308',
            'specific_symbol' => null,
            'payment_type' => PaymentTypeEnum::Transfer,
            'currency' => CurrencyEnum::EUR,
            'rounding_mode' => RoundingModeEnum::None,
            'header_text' => null,
            'footer_text' => null,
            'supplier_swift' => 'TATRSKBX',
            'customer_name' => 'Vzorový Zákazník s.r.o.',
            'customer_ico' => '12345678',
            'customer_dic' => '2012345678',
            'customer_vat_number' => 'SK2012345678',
            'customer_street' => 'Hlavná 1',
            'customer_city' => 'Bratislava',
            'customer_postal_code' => '811 01',
            'customer_country' => 'Slovensko',
            'customer_email' => 'zakaznik@example.sk',
            'supplier_name' => 'Demo Cleaning s.r.o.',
            'supplier_ico' => '87654321',
            'supplier_dic' => '2087654321',
            'supplier_vat_number' => 'SK2087654321',
            'supplier_iban' => 'SK89 7500 0000 0012 3456 7890',
            'supplier_address_line' => 'Obchodná 5',
            'supplier_city' => 'Bratislava',
            'supplier_postal_code' => '811 06',
            'supplier_country' => 'Slovensko',
            'supplier_contact_email' => 'info@democleaning.sk',
            'supplier_contact_phone' => '+421 900 000 000',
            'supplier_registration_info' => 'Spoločnosť zapísaná v OR SR, oddiel Sro, vložka 12345/B',
            'note' => null,
        ]);

        $itemOne = (new InvoiceItem)->forceFill([
            'description' => 'Upratovanie kancelárskych priestorov',
            'quantity' => '2.00',
            'unit' => 'hod',
            'unit_price' => '30.00',
            'discount_percent' => '10.00',
            'vat_rate' => '23.00',
            'line_base' => '54.00',
            'line_vat' => '12.42',
            'line_total' => '66.42',
            'position' => 1,
        ]);

        $itemTwo = (new InvoiceItem)->forceFill([
            'description' => 'Čistenie okien',
            'quantity' => '1.00',
            'unit' => 'ks',
            'unit_price' => '40.00',
            'discount_percent' => '0.00',
            'vat_rate' => '19.00',
            'line_base' => '40.00',
            'line_vat' => '7.60',
            'line_total' => '47.60',
            'position' => 2,
        ]);

        /** @var Collection<int, InvoiceItem> $items */
        $items = collect([$itemOne, $itemTwo]);
        $invoice->setRelation('items', $items);

        return $invoice;
    }
}
