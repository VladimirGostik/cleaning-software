<?php

declare(strict_types=1);

namespace Tests\Feature\RecurringInvoices;

use App\Data\RecurringInvoices\RecurringInvoiceDetailData;
use App\Enums\CustomerPartySourceEnum;
use App\Enums\InvoiceTypeEnum;
use App\Enums\RecurringFrequencyEnum;
use App\Enums\RecurringInvoiceStatusEnum;
use App\Jobs\GenerateRecurringInvoiceJob;
use App\Models\CleaningObject;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\RecurringInvoice;
use App\Models\RecurringInvoiceItem;
use App\Models\Tenant;
use App\Notifications\InvoiceIssued;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

final class RecurringInvoiceBuyerTest extends TestCase
{
    use RefreshDatabase;

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
            'street' => 'Klientska 1',
            'city' => 'Nitra',
            'postal_code' => '94901',
            'country' => 'SK',
        ]);
    }

    /** @param  array<string, mixed>  $overrides */
    private function template(array $overrides = []): RecurringInvoice
    {
        $ri = RecurringInvoice::factory()->create($overrides + [
            'tenant_id' => $this->tenant->id,
            'type' => InvoiceTypeEnum::Monthly,
            'frequency' => RecurringFrequencyEnum::Monthly,
            'day_of_month' => 15,
            'status' => RecurringInvoiceStatusEnum::Active,
            'auto_issue' => false,
            'start_date' => now()->subMonth()->toDateString(),
            'next_run_at' => now()->toDateString(),
            'occurrences_generated' => 0,
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
            'due_days' => 14,
        ]);

        RecurringInvoiceItem::factory()->create([
            'tenant_id' => $this->tenant->id,
            'recurring_invoice_id' => $ri->id,
            'quantity' => 1.0,
            'unit_price' => 100.0,
            'position' => 0,
        ]);

        return $ri;
    }

    /** @param  array<string, mixed>  $billing */
    private function house(array $billing = []): CleaningObject
    {
        return CleaningObject::factory()
            ->withBillingIdentity($billing + [
                'billing_name' => 'Bytovy dom Lipova 5',
                'billing_ico' => '22222222',
                'billing_dic' => '2022222222',
                'billing_street' => 'Lipova 5',
                'billing_city' => 'Zilina',
                'billing_postal_code' => '01001',
            ])
            ->create(['tenant_id' => $this->tenant->id, 'client_id' => $this->client->id]);
    }

    public function test_detail_buyer_resolves_the_house_billing_identity(): void
    {
        $ri = $this->template(['client_id' => $this->client->id, 'cleaning_object_id' => $this->house()->id]);

        $buyer = RecurringInvoiceDetailData::fromModel($ri)->buyer;

        $this->assertSame(CustomerPartySourceEnum::ObjectBilling, $buyer->source);
        $this->assertSame('Bytovy dom Lipova 5', $buyer->name);
        $this->assertSame('Spravca s.r.o.', $buyer->represented_by);
        $this->assertSame('22222222', $buyer->ico);
        $this->assertSame('2022222222', $buyer->dic);
        $this->assertSame('Lipova 5', $buyer->street);
        $this->assertSame('Zilina', $buyer->city);
        $this->assertSame('01001', $buyer->postal_code);
        $this->assertNull($buyer->email);
    }

    public function test_detail_buyer_matches_the_generated_invoice(): void
    {
        $ri = $this->template(['client_id' => $this->client->id, 'cleaning_object_id' => $this->house()->id]);
        $buyer = RecurringInvoiceDetailData::fromModel($ri)->buyer;

        GenerateRecurringInvoiceJob::dispatchSync($ri->id);

        $invoice = Invoice::query()->where('tenant_id', $this->tenant->id)->firstOrFail();
        $this->assertSame($buyer->name, $invoice->customer_name);
        $this->assertSame($buyer->represented_by, $invoice->customer_represented_by);
        $this->assertSame($buyer->ico, $invoice->customer_ico);
        $this->assertSame($buyer->dic, $invoice->customer_dic);
        $this->assertSame($buyer->street, $invoice->customer_street);
        $this->assertSame($buyer->city, $invoice->customer_city);
        $this->assertSame($buyer->postal_code, $invoice->customer_postal_code);
        $this->assertSame($buyer->country, $invoice->customer_country);
    }

    public function test_detail_buyer_falls_back_to_the_object_address(): void
    {
        $house = $this->house(['billing_street' => null, 'billing_city' => null, 'billing_postal_code' => null]);
        $house->update(['street' => 'Objektova 9', 'city' => 'Presov', 'postal_code' => '08001']);
        $ri = $this->template(['client_id' => $this->client->id, 'cleaning_object_id' => $house->id]);

        $buyer = RecurringInvoiceDetailData::fromModel($ri)->buyer;

        $this->assertSame('Objektova 9', $buyer->street);
        $this->assertSame('Presov', $buyer->city);
        $this->assertSame('08001', $buyer->postal_code);
    }

    public function test_detail_buyer_is_the_client_when_the_object_has_no_billing_identity(): void
    {
        $object = CleaningObject::factory()->create(['tenant_id' => $this->tenant->id, 'client_id' => $this->client->id, 'billing_name' => null]);
        $ri = $this->template(['client_id' => $this->client->id, 'cleaning_object_id' => $object->id]);

        $buyer = RecurringInvoiceDetailData::fromModel($ri)->buyer;

        $this->assertSame(CustomerPartySourceEnum::Client, $buyer->source);
        $this->assertSame('Spravca s.r.o.', $buyer->name);
        $this->assertNull($buyer->represented_by);
        $this->assertSame('Klientska 1', $buyer->street);
    }

    public function test_detail_buyer_for_a_standalone_template_is_entered_with_default_country(): void
    {
        $ri = $this->template(['client_id' => null, 'cleaning_object_id' => null, 'customer_name' => 'Rucny odberatel', 'customer_email' => 'rucny@example.com']);

        $buyer = RecurringInvoiceDetailData::fromModel($ri)->buyer;

        $this->assertSame(CustomerPartySourceEnum::Entered, $buyer->source);
        $this->assertSame('Rucny odberatel', $buyer->name);
        $this->assertSame('SK', $buyer->country);
        $this->assertSame('rucny@example.com', $buyer->email);
    }

    public function test_billing_email_is_never_a_default_recipient(): void
    {
        $log = Log::spy();
        $house = $this->house();
        $house->forceFill(['billing_email' => 'faktury@example.com'])->save();
        $ri = $this->template([
            'client_id' => $this->client->id,
            'cleaning_object_id' => $house->id,
            'auto_issue' => true,
            'auto_send' => true,
        ]);

        GenerateRecurringInvoiceJob::dispatchSync($ri->id);

        $invoice = Invoice::query()->where('tenant_id', $this->tenant->id)->firstOrFail();
        $this->assertNull($invoice->customer_email);
        $log->shouldHaveReceived('warning', fn (string $message): bool => $message === 'recurring_invoice.auto_send.skipped_no_email');
    }

    public function test_template_email_is_carried_and_sent(): void
    {
        Notification::fake();
        $ri = $this->template([
            'client_id' => $this->client->id,
            'cleaning_object_id' => $this->house()->id,
            'customer_email' => 'sablona@example.com',
            'auto_issue' => true,
            'auto_send' => true,
        ]);
        $this->tenant->update(['ico' => '12345678', 'dic' => '2012345678', 'address_line' => 'Hlavna 1', 'city' => 'Bratislava', 'postal_code' => '81101', 'iban' => 'SK8975000000000123456789', 'contact_email' => 'f@example.com', 'contact_phone' => '+421900000000']);

        GenerateRecurringInvoiceJob::dispatchSync($ri->id);

        $invoice = Invoice::query()->where('tenant_id', $this->tenant->id)->firstOrFail();
        $this->assertSame('sablona@example.com', $invoice->customer_email);
        Notification::assertSentOnDemand(InvoiceIssued::class);
    }
}
