<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Models\CleaningObject;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Quote;
use App\Models\RecurringInvoice;
use App\Models\Tenant;
use Illuminate\Http\UploadedFile;

trait BuildsFullInvoices
{
    /**
     * Columns of an invoice that a copy keeps from its source (ids of client/object/tenant are added by the caller).
     *
     * @return array<string, mixed>
     */
    protected function carriedInvoiceColumns(): array
    {
        return [
            'type' => 'monthly',
            'is_vat_payer' => true,
            'vat_rate' => '20.00',
            'subtotal' => '300.00',
            'vat_amount' => '60.00',
            'total' => '360.00',
            'deposit' => '10.00',
            'rounding_amount' => '0.05',
            'vat_breakdown' => [['rate' => 20.0, 'base' => 300.0, 'vat' => 60.0, 'total' => 360.0]],
            'constant_symbol' => '0308',
            'specific_symbol' => '55',
            'payment_type' => 'cash',
            'currency' => 'CZK',
            'rounding_mode' => 'cash_005',
            'header_text' => 'Header text',
            'footer_text' => 'Footer text',
            'customer_name' => 'Buyer s.r.o.',
            'customer_representative' => 'Jana Kupujuca',
            'customer_represented_by' => 'Parent Client a.s.',
            'customer_ico' => '12345678',
            'customer_peppol_participant_id' => '0245:2012345678',
            'customer_dic' => '2012345678',
            'customer_vat_number' => 'SK2012345678',
            'customer_street' => 'Kupujuca 1',
            'customer_city' => 'Zvolen',
            'customer_postal_code' => '960 01',
            'customer_country' => 'CZ',
            'customer_email' => 'buyer@example.test',
            'object_name' => 'Object Alpha',
            'object_street' => 'Objektova 2',
            'object_city' => 'Banska Bystrica',
            'object_postal_code' => '974 01',
            'supplier_name' => 'Supplier s.r.o.',
            'supplier_ico' => '87654321',
            'supplier_dic' => '2087654321',
            'supplier_vat_number' => 'SK2087654321',
            'supplier_iban' => 'SK8975000000000123456789',
            'supplier_swift' => 'TATRSKBX',
            'supplier_address_line' => 'Dodavatelska 3',
            'supplier_city' => 'Bratislava',
            'supplier_postal_code' => '811 01',
            'supplier_country' => 'SK',
            'supplier_contact_email' => 'supplier@example.test',
            'supplier_contact_phone' => '+421900111222',
            'supplier_registration_info' => 'Registered in OR BA I',
            'supplier_peppol_participant_id' => '0245:2087654321',
            'note' => 'Internal note',
        ];
    }

    /**
     * Item columns a copy keeps from the first, second and third source item (before any negation).
     *
     * @return list<array<string, mixed>>
     */
    protected function sourceItemColumns(): array
    {
        return [
            [
                'description' => 'Window cleaning', 'quantity' => '2.50', 'unit' => 'hod', 'unit_price' => '40.00',
                'discount_percent' => '10.00', 'vat_rate' => '20.00', 'line_base' => '90.00', 'line_vat' => '18.00',
                'line_total' => '108.00', 'position' => 0,
            ],
            [
                'description' => 'Floor care', 'quantity' => '1.00', 'unit' => null, 'unit_price' => '150.00',
                'discount_percent' => '0.00', 'vat_rate' => '20.00', 'line_base' => '150.00', 'line_vat' => '30.00',
                'line_total' => '180.00', 'position' => 1,
            ],
            [
                'description' => 'Consumables', 'quantity' => '3.00', 'unit' => 'ks', 'unit_price' => '20.00',
                'discount_percent' => '5.00', 'vat_rate' => '0.00', 'line_base' => '57.00', 'line_vat' => '0.00',
                'line_total' => '57.00', 'position' => 2,
            ],
        ];
    }

    /**
     * A fully filled source invoice: every nullable column set, linked client/object/quote/recurring template, signature, three items.
     *
     * @param  array<string, mixed>  $overrides
     */
    protected function fullInvoiceSource(Tenant $tenant, array $overrides = []): Invoice
    {
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $object = CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);
        $quote = Quote::factory()->create(['tenant_id' => $tenant->id]);
        $recurring = RecurringInvoice::factory()->create(['tenant_id' => $tenant->id]);
        $earlier = Invoice::factory()->issued()->create(['tenant_id' => $tenant->id]);
        $media = $tenant->addMedia(UploadedFile::fake()->image('source-signature.png', 80, 40))->toMediaCollection('signature');

        $invoice = Invoice::factory()->create([
            ...$this->carriedInvoiceColumns(),
            'tenant_id' => $tenant->id,
            'client_id' => $client->id,
            'cleaning_object_id' => $object->id,
            'recurring_invoice_id' => $recurring->id,
            'quote_id' => $quote->id,
            'credited_invoice_id' => $earlier->id,
            'status' => 'paid',
            'number' => 'FA-2026-0042',
            'variable_symbol' => '20260042',
            'period_from' => '2026-10-01',
            'period_to' => '2026-10-31',
            'issue_date' => '2026-10-05',
            'delivery_date' => '2026-10-04',
            'due_date' => '2026-10-19',
            'issued_at' => '2026-10-05 08:00:00',
            'issued_by_name' => 'Original Issuer',
            'sent_at' => '2026-10-06 09:30:00',
            'paid_at' => '2026-10-20 12:00:00',
            'cancelled_at' => '2026-10-25 00:00:00',
            'paid_amount' => '350.00',
            'supplier_signature_media_id' => $media->id,
            'efa_status' => 'submitted',
            'efa_id' => 'EFA-123',
            'efa_submitted_at' => '2026-10-07 10:00:00',
            'efa_last_error' => 'Earlier failure',
            ...$overrides,
        ]);

        foreach ($this->sourceItemColumns() as $columns) {
            InvoiceItem::factory()->create([...$columns, 'tenant_id' => $tenant->id, 'invoice_id' => $invoice->id]);
        }

        return $invoice->load('items');
    }
}
