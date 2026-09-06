<?php

declare(strict_types=1);

namespace Tests\Feature\Schedule;

use App\Enums\TaskFrequencyEnum;
use App\Events\ContractSigned;
use App\Jobs\GenerateScheduledJobsJob;
use App\Listeners\GenerateWorkBreakdownFromSignedContract;
use App\Models\CleaningObject;
use App\Models\Client;
use App\Models\Contract;
use App\Models\Quote;
use App\Models\QuoteItem;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\WorkBreakdown;
use App\Services\ContractService;
use App\Services\WorkBreakdownService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

final class WorkBreakdownGenerationTest extends TestCase
{
    use RefreshDatabase;

    public function test_signing_a_service_agreement_contract_generates_breakdown_with_tasks(): void
    {
        Bus::fake();

        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $object = CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);
        $quote = Quote::factory()->forClient($client)->forObject($object)->accepted()->create(['tenant_id' => $tenant->id]);
        QuoteItem::factory()->create([
            'tenant_id' => $tenant->id,
            'quote_id' => $quote->id,
            'description' => 'Umývanie okien',
            'frequency' => TaskFrequencyEnum::Weekly1x,
            'position' => 0,
        ]);
        $contract = Contract::factory()->fromQuote($quote)->draft()->create(['tenant_id' => $tenant->id]);

        app(ContractService::class)->sign($contract);

        $breakdown = WorkBreakdown::where('contract_id', $contract->id)->firstOrFail();
        $this->assertSame($object->id, $breakdown->cleaning_object_id);
        $this->assertSame($quote->id, $breakdown->source_quote_id);
        $this->assertCount(1, $breakdown->tasks);
        $this->assertSame('Umývanie okien', $breakdown->tasks->sole()->name);
        $this->assertSame(TaskFrequencyEnum::Weekly1x, $breakdown->tasks->sole()->frequency);
    }

    public function test_listener_dispatches_generate_scheduled_jobs_job(): void
    {
        Bus::fake();

        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $object = CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);
        $quote = Quote::factory()->forClient($client)->forObject($object)->accepted()->create(['tenant_id' => $tenant->id]);
        QuoteItem::factory()->create(['tenant_id' => $tenant->id, 'quote_id' => $quote->id]);
        $contract = Contract::factory()->fromQuote($quote)->draft()->create(['tenant_id' => $tenant->id]);

        app(ContractService::class)->sign($contract);

        Bus::assertDispatched(GenerateScheduledJobsJob::class);
    }

    public function test_generation_is_idempotent_when_called_twice(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $object = CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);
        $quote = Quote::factory()->forClient($client)->forObject($object)->accepted()->create(['tenant_id' => $tenant->id]);
        QuoteItem::factory()->create(['tenant_id' => $tenant->id, 'quote_id' => $quote->id]);
        $contract = Contract::factory()->fromQuote($quote)->active()->create(['tenant_id' => $tenant->id]);

        $service = app(WorkBreakdownService::class);
        $first = $service->generateFromContract($contract);
        $second = $service->generateFromContract($contract);

        $this->assertNotNull($first);
        $this->assertSame($first->id, $second?->id);
        $this->assertSame(1, WorkBreakdown::where('contract_id', $contract->id)->count());
    }

    public function test_returns_null_for_non_service_agreement_category(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);
        $membership = TenantMembership::factory()->create(['tenant_id' => $tenant->id]);
        $contract = Contract::factory()
            ->forMembership($membership)
            ->create(['tenant_id' => $tenant->id]);

        $result = app(WorkBreakdownService::class)->generateFromContract($contract);

        $this->assertNull($result);
        $this->assertSame(0, WorkBreakdown::where('contract_id', $contract->id)->count());
    }

    public function test_returns_null_when_contract_has_no_source_quote(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $object = CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);
        $contract = Contract::factory()->forObject($object)->create(['tenant_id' => $tenant->id, 'quote_id' => null]);

        $result = app(WorkBreakdownService::class)->generateFromContract($contract);

        $this->assertNull($result);
    }

    public function test_listener_is_noop_for_contract_without_quote(): void
    {
        Bus::fake();

        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $object = CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);
        $contract = Contract::factory()->forObject($object)->create(['tenant_id' => $tenant->id, 'quote_id' => null]);

        app(GenerateWorkBreakdownFromSignedContract::class)->handle(
            new ContractSigned($tenant->id, $contract->id),
        );

        $this->assertSame(0, WorkBreakdown::where('contract_id', $contract->id)->count());
        Bus::assertNotDispatched(GenerateScheduledJobsJob::class);
    }

    public function test_generate_from_contract_sets_tenant_id_explicitly_without_bound_tenant(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $object = CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);
        $quote = Quote::factory()->forClient($client)->forObject($object)->accepted()->create(['tenant_id' => $tenant->id]);
        QuoteItem::factory()->create(['tenant_id' => $tenant->id, 'quote_id' => $quote->id]);
        $contract = Contract::factory()->fromQuote($quote)->active()->create(['tenant_id' => $tenant->id]);

        // Simulate worker context — no tenant bound in the container.
        app()->forgetInstance('current_tenant_id');

        $breakdown = app(WorkBreakdownService::class)->generateFromContract($contract);

        $this->assertNotNull($breakdown);
        $this->assertSame($tenant->id, $breakdown->tenant_id);
    }
}
