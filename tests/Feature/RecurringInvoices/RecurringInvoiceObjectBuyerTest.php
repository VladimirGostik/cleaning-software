<?php

declare(strict_types=1);

namespace Tests\Feature\RecurringInvoices;

use App\Data\RecurringInvoices\RecurringInvoiceDetailData;
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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

final class RecurringInvoiceObjectBuyerTest extends TestCase
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
            'peppol_participant_id' => '0245:2011111111',
        ]);
    }

    /** @param  array<string, mixed>  $overrides */
    private function dueTemplate(array $overrides): RecurringInvoice
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

    private function house(): CleaningObject
    {
        return CleaningObject::factory()
            ->withBillingIdentity(['billing_name' => 'Bytovy dom Lipova 5', 'billing_peppol_participant_id' => '0245:2022222222'])
            ->create(['tenant_id' => $this->tenant->id, 'client_id' => $this->client->id]);
    }

    public function test_object_linked_template_generates_an_invoice_for_the_house(): void
    {
        $ri = $this->dueTemplate(['client_id' => $this->client->id, 'cleaning_object_id' => $this->house()->id]);

        GenerateRecurringInvoiceJob::dispatchSync($ri->id);

        $invoice = Invoice::query()->where('tenant_id', $this->tenant->id)->firstOrFail();
        $this->assertSame('Bytovy dom Lipova 5', $invoice->customer_name);
        $this->assertSame('0245:2022222222', $invoice->customer_peppol_participant_id);
        $this->assertSame('Spravca s.r.o.', $invoice->customer_represented_by);
    }

    public function test_client_linked_template_keeps_the_client_peppol_id(): void
    {
        $ri = $this->dueTemplate(['client_id' => $this->client->id, 'cleaning_object_id' => null]);

        GenerateRecurringInvoiceJob::dispatchSync($ri->id);

        $invoice = Invoice::query()->where('tenant_id', $this->tenant->id)->firstOrFail();
        $this->assertSame('Spravca s.r.o.', $invoice->customer_name);
        $this->assertSame('0245:2011111111', $invoice->customer_peppol_participant_id);
        $this->assertNull($invoice->customer_represented_by);
    }

    public function test_display_name_prefers_the_house_billing_name(): void
    {
        $this->dueTemplate(['client_id' => $this->client->id, 'cleaning_object_id' => $this->house()->id]);
        $this->actingAsTenantUser('Admin', $this->tenant);

        $this->get(route('recurring-invoices.index'))->assertInertia(
            fn (AssertableInertia $page) => $page->where('recurringInvoices.data.0.customer_display_name', 'Bytovy dom Lipova 5'),
        );
    }

    public function test_detail_buyer_name_prefers_the_house_billing_name(): void
    {
        $ri = $this->dueTemplate(['client_id' => $this->client->id, 'cleaning_object_id' => $this->house()->id]);
        $ri->load(['client', 'cleaningObject']);

        $this->assertSame('Bytovy dom Lipova 5', RecurringInvoiceDetailData::fromModel($ri)->buyer->name);
    }
}
