<?php

declare(strict_types=1);

namespace Tests\Feature\Quotes;

use App\Enums\QuoteStatusEnum;
use App\Enums\SubscriptionPlanEnum;
use App\Models\CleaningObject;
use App\Models\Client;
use App\Models\Contract;
use App\Models\Invoice;
use App\Models\Quote;
use App\Models\QuoteItem;
use App\Models\Tenant;
use App\Services\QuoteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class QuoteConversionTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // convertToInvoice — happy path
    // -------------------------------------------------------------------------

    public function test_convert_to_invoice_creates_invoice_linked_to_quote(): void
    {
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);
        $tenant = Tenant::where('owner_id', $user->id)->first();
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);

        $quote = Quote::factory()->accepted()->create([
            'tenant_id' => $tenant->id,
            'client_id' => $client->id,
            'note' => 'Test note',
        ]);

        QuoteItem::factory()->create([
            'tenant_id' => $tenant->id,
            'quote_id' => $quote->id,
            'name' => 'Upratovanie',
            'frequency' => '2x týždenne',
            'quantity' => '1.00',
            'unit_price' => '100.00',
            'line_base' => '100.00',
            'line_vat' => '0.00',
            'line_total' => '100.00',
        ]);

        $invoice = app(QuoteService::class)->convertToInvoice($quote);

        $this->assertInstanceOf(Invoice::class, $invoice);
        $this->assertSame($quote->id, $invoice->quote_id);
        $this->assertSame($client->id, $invoice->client_id);
        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
            'quote_id' => $quote->id,
        ]);
    }

    // -------------------------------------------------------------------------
    // convertToInvoice — fail: not accepted
    // -------------------------------------------------------------------------

    public function test_convert_to_invoice_throws_when_quote_is_draft(): void
    {
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);
        $tenant = Tenant::where('owner_id', $user->id)->first();
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);

        $quote = Quote::factory()->create([
            'tenant_id' => $tenant->id,
            'client_id' => $client->id,
            'status' => QuoteStatusEnum::Draft,
        ]);

        $this->expectException(ValidationException::class);

        app(QuoteService::class)->convertToInvoice($quote);
    }

    // -------------------------------------------------------------------------
    // convertToContract — happy path
    // -------------------------------------------------------------------------

    public function test_convert_to_contract_creates_contract_linked_to_quote(): void
    {
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);
        $tenant = Tenant::where('owner_id', $user->id)->first();
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $object = CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);

        $quote = Quote::factory()->accepted()->create([
            'tenant_id' => $tenant->id,
            'client_id' => $client->id,
            'cleaning_object_id' => $object->id,
            'subject' => 'Zmluva o upratovaní',
        ]);

        QuoteItem::factory()->create([
            'tenant_id' => $tenant->id,
            'quote_id' => $quote->id,
            'name' => 'Týždenné upratovanie',
            'quantity' => '1.00',
            'unit_price' => '200.00',
            'line_base' => '200.00',
            'line_vat' => '0.00',
            'line_total' => '200.00',
        ]);

        $contract = app(QuoteService::class)->convertToContract($quote);

        $this->assertInstanceOf(Contract::class, $contract);
        $this->assertSame($quote->id, $contract->quote_id);
        $this->assertSame($object->id, $contract->contractable_id);
        $this->assertDatabaseHas('contracts', [
            'id' => $contract->id,
            'quote_id' => $quote->id,
        ]);
    }

    // -------------------------------------------------------------------------
    // convertToContract — fail: no object
    // -------------------------------------------------------------------------

    public function test_convert_to_contract_throws_when_no_cleaning_object(): void
    {
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);
        $tenant = Tenant::where('owner_id', $user->id)->first();
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);

        $quote = Quote::factory()->accepted()->create([
            'tenant_id' => $tenant->id,
            'client_id' => $client->id,
            'cleaning_object_id' => null,
        ]);

        $this->expectException(ValidationException::class);

        app(QuoteService::class)->convertToContract($quote);
    }

    // -------------------------------------------------------------------------
    // convertToContract — fail: not accepted
    // -------------------------------------------------------------------------

    public function test_convert_to_contract_throws_when_quote_is_sent(): void
    {
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);
        $tenant = Tenant::where('owner_id', $user->id)->first();
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $object = CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);

        $quote = Quote::factory()->sent()->create([
            'tenant_id' => $tenant->id,
            'client_id' => $client->id,
            'cleaning_object_id' => $object->id,
        ]);

        $this->expectException(ValidationException::class);

        app(QuoteService::class)->convertToContract($quote);
    }

    // -------------------------------------------------------------------------
    // item description includes frequency in invoice
    // -------------------------------------------------------------------------

    public function test_invoice_item_description_includes_frequency(): void
    {
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);
        $tenant = Tenant::where('owner_id', $user->id)->first();
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);

        $quote = Quote::factory()->accepted()->create([
            'tenant_id' => $tenant->id,
            'client_id' => $client->id,
        ]);

        QuoteItem::factory()->create([
            'tenant_id' => $tenant->id,
            'quote_id' => $quote->id,
            'name' => 'Upratovanie',
            'frequency' => '3x týždenne',
            'quantity' => '1.00',
            'unit_price' => '150.00',
            'line_base' => '150.00',
            'line_vat' => '0.00',
            'line_total' => '150.00',
        ]);

        $invoice = app(QuoteService::class)->convertToInvoice($quote);

        $invoice->loadMissing('items');
        $this->assertStringContainsString('3x týždenne', $invoice->items->first()->description);
    }
}
