<?php

declare(strict_types=1);

namespace Tests\Feature\Invoices;

use App\Data\Invoices\InvoiceIssueData;
use App\Data\Invoices\InvoiceItemData;
use App\Data\Invoices\InvoiceUpsertData;
use App\Enums\InvoiceTypeEnum;
use App\Models\CleaningObject;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Tenant;
use App\Services\InvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ViewErrorBag;
use Tests\Support\BuildsEfaInvoices;
use Tests\TestCase;

final class InvoiceObjectBuyerTest extends TestCase
{
    use BuildsEfaInvoices, RefreshDatabase;

    private Tenant $tenant;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->bindTenant($this->tenant);
        $this->client = Client::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Spravca s.r.o.',
            'ico' => '11111111',
            'dic' => '2011111111',
            'peppol_participant_id' => '0245:2011111111',
            'street' => 'Klientska 1',
            'city' => 'Zilina',
            'postal_code' => '01001',
        ]);
    }

    /** @param  array<string, mixed>  $billing */
    private function house(array $billing = []): CleaningObject
    {
        return CleaningObject::factory()->withBillingIdentity([
            'billing_name' => 'Bytovy dom Lipova 5',
            'billing_ico' => '22222222',
            'billing_dic' => '2022222222',
            'billing_vat_number' => 'SK2022222222',
            'billing_peppol_participant_id' => '0245:2022222222',
            ...$billing,
        ])->create([
            'tenant_id' => $this->tenant->id,
            'client_id' => $this->client->id,
            'street' => 'Lipova 5',
            'city' => 'Bratislava',
            'postal_code' => '81101',
            'country' => 'SK',
        ]);
    }

    /** @param  array<string, mixed>  $overrides */
    private function upsert(?CleaningObject $object, array $overrides = []): InvoiceUpsertData
    {
        return InvoiceUpsertData::from(array_merge([
            'client_id' => $this->client->id,
            'cleaning_object_id' => $object?->id,
            'type' => InvoiceTypeEnum::OneOff->value,
            'issue_date' => now()->toDateString(),
            'delivery_date' => now()->toDateString(),
            'due_date' => now()->addDays(14)->toDateString(),
            'customer_name' => null,
            'customer_representative' => null,
            'customer_ico' => null,
            'customer_dic' => null,
            'customer_vat_number' => null,
            'customer_street' => null,
            'customer_city' => null,
            'customer_postal_code' => null,
            'customer_country' => null,
            'customer_email' => null,
            'note' => null,
            'items' => [new InvoiceItemData(id: null, description: 'Upratovanie', quantity: 1, unit: 'hod', unit_price: 50, discount_percent: 0, vat_rate: 0)],
            'constant_symbol' => null,
            'specific_symbol' => null,
            'header_text' => null,
            'footer_text' => null,
            'deposit' => 0,
            'payment_type' => 'transfer',
            'currency' => 'EUR',
            'rounding_mode' => 'none',
        ], $overrides));
    }

    private function create(?CleaningObject $object): Invoice
    {
        return app(InvoiceService::class)->create($this->upsert($object));
    }

    private function issued(?CleaningObject $object): Invoice
    {
        $invoice = $this->create($object);

        return app(InvoiceService::class)->issue($invoice, new InvoiceIssueData(number: null), null);
    }

    public function test_house_with_billing_identity_becomes_the_buyer(): void
    {
        $object = $this->house();

        $invoice = $this->create($object);

        $this->assertSame('Bytovy dom Lipova 5', $invoice->customer_name);
        $this->assertSame('22222222', $invoice->customer_ico);
        $this->assertSame('2022222222', $invoice->customer_dic);
        $this->assertSame('SK2022222222', $invoice->customer_vat_number);
        $this->assertSame('0245:2022222222', $invoice->customer_peppol_participant_id);
        $this->assertSame('Spravca s.r.o.', $invoice->customer_represented_by);
        $this->assertSame('Lipova 5', $invoice->customer_street);
        $this->assertSame('Bratislava', $invoice->customer_city);
        $this->assertSame('81101', $invoice->customer_postal_code);
        $this->assertSame('SK', $invoice->customer_country);
        $this->assertSame($object->name, $invoice->object_name);
    }

    public function test_billing_address_is_used_verbatim_when_set(): void
    {
        $object = $this->house(['billing_street' => 'Fakturacna 9', 'billing_city' => 'Nitra', 'billing_postal_code' => '94901']);

        $invoice = $this->create($object);

        $this->assertSame('Fakturacna 9', $invoice->customer_street);
        $this->assertSame('Nitra', $invoice->customer_city);
        $this->assertSame('94901', $invoice->customer_postal_code);
    }

    public function test_partial_billing_address_is_not_mixed_with_the_object_address(): void
    {
        $object = $this->house(['billing_city' => 'Nitra']);

        $invoice = $this->create($object);

        $this->assertSame('Nitra', $invoice->customer_city);
        $this->assertNull($invoice->customer_street);
        $this->assertNull($invoice->customer_postal_code);
    }

    public function test_object_without_billing_identity_keeps_the_client_as_buyer(): void
    {
        $object = CleaningObject::factory()->create(['tenant_id' => $this->tenant->id, 'client_id' => $this->client->id]);

        $invoice = $this->create($object);

        $this->assertSame('Spravca s.r.o.', $invoice->customer_name);
        $this->assertSame('0245:2011111111', $invoice->customer_peppol_participant_id);
        $this->assertSame('Klientska 1', $invoice->customer_street);
        $this->assertNull($invoice->customer_represented_by);
    }

    public function test_client_only_invoice_is_unchanged(): void
    {
        $invoice = $this->create(null);

        $this->assertSame('Spravca s.r.o.', $invoice->customer_name);
        $this->assertSame('11111111', $invoice->customer_ico);
        $this->assertNull($invoice->customer_represented_by);
    }

    public function test_efa_xml_routes_to_the_house_peppol_endpoint(): void
    {
        $this->actingAsTenantUser('Admin', $this->tenant);
        $invoice = $this->issued($this->house());

        $response = $this->get(route('invoices.efa.xml', $invoice));

        $response->assertOk();
        $xp = $this->loadUbl((string) $response->getContent());
        $this->assertSame('Bytovy dom Lipova 5', $this->xv($xp, '//cac:AccountingCustomerParty//cbc:Name'));
        $this->assertSame('2022222222', $this->xv($xp, '//cac:AccountingCustomerParty//cbc:EndpointID'));
        $this->assertSame('0245', $this->xv($xp, '//cac:AccountingCustomerParty//cbc:EndpointID/@schemeID'));
    }

    public function test_efa_xml_falls_back_to_the_house_dic_endpoint(): void
    {
        $this->actingAsTenantUser('Admin', $this->tenant);
        $invoice = $this->issued($this->house(['billing_peppol_participant_id' => null, 'billing_dic' => '2033333333']));

        $response = $this->get(route('invoices.efa.xml', $invoice));

        $response->assertOk();
        $xp = $this->loadUbl((string) $response->getContent());
        $this->assertSame('2033333333', $this->xv($xp, '//cac:AccountingCustomerParty//cbc:EndpointID'));
        $this->assertSame('0245', $this->xv($xp, '//cac:AccountingCustomerParty//cbc:EndpointID/@schemeID'));
    }

    public function test_efa_xml_reports_missing_customer_dic_for_a_house_without_dic_and_peppol(): void
    {
        $this->actingAsTenantUser('Admin', $this->tenant);
        $invoice = $this->issued($this->house(['billing_peppol_participant_id' => null, 'billing_dic' => null]));

        $this->get(route('invoices.efa.xml', $invoice))->assertSessionHasErrors('efa');

        $errors = session('errors');
        $this->assertInstanceOf(ViewErrorBag::class, $errors);
        $this->assertStringContainsString(__('app.efa_missing_customer_dic'), $errors->first('efa'));
    }

    public function test_issued_invoice_keeps_its_snapshot_when_the_house_changes(): void
    {
        $this->actingAsTenantUser('Admin', $this->tenant);
        $object = $this->house();
        $invoice = $this->issued($object);

        $object->update(['billing_name' => 'Zmeneny subjekt', 'billing_ico' => '99999999']);

        $this->get(route('invoices.show', $invoice))->assertOk();
        $invoice->refresh();
        $this->assertSame('Bytovy dom Lipova 5', $invoice->customer_name);
        $this->assertSame('22222222', $invoice->customer_ico);
    }

    public function test_draft_is_resnapshotted_on_update(): void
    {
        $object = $this->house();
        $invoice = $this->create($object);

        $object->update(['billing_name' => 'Zmeneny subjekt', 'billing_ico' => '99999999', 'billing_dic' => '2099999999']);
        $updated = app(InvoiceService::class)->update($invoice, $this->upsert($object));

        $this->assertSame('Zmeneny subjekt', $updated->customer_name);
        $this->assertSame('99999999', $updated->customer_ico);
        $this->assertSame('2099999999', $updated->customer_dic);
    }

    /** @return array<string, mixed> */
    private function staleSnapshot(Invoice $invoice): array
    {
        return $invoice->only(['customer_name', 'customer_ico', 'customer_dic', 'customer_vat_number', 'customer_street', 'customer_city', 'customer_postal_code', 'customer_country']);
    }

    private function sparseClient(): void
    {
        $this->client->update(['ico' => null, 'dic' => null, 'vat_number' => null, 'peppol_participant_id' => null, 'street' => null, 'city' => null, 'postal_code' => null]);
    }

    public function test_draft_moved_from_house_to_plain_object_keeps_no_house_data(): void
    {
        $this->sparseClient();
        $invoice = $this->create($this->house());
        $plain = CleaningObject::factory()->create(['tenant_id' => $this->tenant->id, 'client_id' => $this->client->id]);

        $updated = app(InvoiceService::class)->update($invoice, $this->upsert($plain, $this->staleSnapshot($invoice)));

        $this->assertSame('Spravca s.r.o.', $updated->customer_name);
        $this->assertNull($updated->customer_ico);
        $this->assertNull($updated->customer_dic);
        $this->assertNull($updated->customer_vat_number);
        $this->assertNull($updated->customer_street);
        $this->assertNull($updated->customer_peppol_participant_id);
        $this->assertNull($updated->customer_represented_by);
    }

    public function test_draft_moved_from_house_to_no_object_keeps_no_house_data(): void
    {
        $this->sparseClient();
        $invoice = $this->create($this->house());

        $updated = app(InvoiceService::class)->update($invoice, $this->upsert(null, $this->staleSnapshot($invoice)));

        $this->assertSame('Spravca s.r.o.', $updated->customer_name);
        $this->assertNull($updated->customer_ico);
        $this->assertNull($updated->customer_dic);
        $this->assertNull($updated->customer_vat_number);
        $this->assertNull($updated->customer_street);
        $this->assertNull($updated->customer_represented_by);
    }

    public function test_house_with_name_only_does_not_inherit_the_client_identity(): void
    {
        $object = $this->house([
            'billing_ico' => null, 'billing_dic' => null, 'billing_vat_number' => null, 'billing_peppol_participant_id' => null,
        ]);

        $invoice = $this->create($object);

        $this->assertSame('Bytovy dom Lipova 5', $invoice->customer_name);
        $this->assertNull($invoice->customer_ico);
        $this->assertNull($invoice->customer_dic);
        $this->assertNull($invoice->customer_vat_number);
        $this->assertNull($invoice->customer_peppol_participant_id);
    }

    public function test_efa_for_a_name_only_house_is_not_routed_to_the_client_or_a_stale_house(): void
    {
        $this->actingAsTenantUser('Admin', $this->tenant);
        $this->sparseClient();
        $invoice = $this->create($this->house());
        $plain = CleaningObject::factory()->create(['tenant_id' => $this->tenant->id, 'client_id' => $this->client->id]);
        $invoice = app(InvoiceService::class)->update($invoice, $this->upsert($plain, $this->staleSnapshot($invoice)));
        $invoice = app(InvoiceService::class)->issue($invoice, new InvoiceIssueData(number: null), null);

        $this->get(route('invoices.efa.xml', $invoice))->assertSessionHasErrors('efa');

        $errors = session('errors');
        $this->assertInstanceOf(ViewErrorBag::class, $errors);
        $this->assertStringContainsString(__('app.efa_missing_customer_dic'), $errors->first('efa'));
    }

    public function test_duplicate_and_credit_note_carry_represented_by(): void
    {
        $invoice = $this->issued($this->house());
        $service = app(InvoiceService::class);

        $this->assertSame('Spravca s.r.o.', $service->duplicate($invoice)->customer_represented_by);
        $this->assertSame('Spravca s.r.o.', $service->cancel($invoice, null)->customer_represented_by);
    }

    public function test_pdf_prints_represented_by_only_when_set(): void
    {
        $with = $this->create($this->house());
        $without = $this->create(null);

        $htmlWith = View::make('pdf.invoices.kegos', ['invoice' => $with, 'qrDataUri' => null])->render();
        $htmlWithout = View::make('pdf.invoices.kegos', ['invoice' => $without, 'qrDataUri' => null])->render();

        $label = __('app.invoice_pdf_represented_by');
        $this->assertStringContainsString($label.': Spravca s.r.o.', $htmlWith);
        $this->assertStringNotContainsString($label, $htmlWithout);
    }
}
