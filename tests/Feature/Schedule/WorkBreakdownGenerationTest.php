<?php

declare(strict_types=1);

namespace Tests\Feature\Schedule;

use App\Enums\ContractCategoryEnum;
use App\Enums\ContractStatusEnum;
use App\Enums\ContractTermTypeEnum;
use App\Enums\EmploymentContractTypeEnum;
use App\Enums\TaskFrequencyEnum;
use App\Models\CleaningObject;
use App\Models\Client;
use App\Models\Contract;
use App\Models\EmploymentContract;
use App\Models\Quote;
use App\Models\QuoteItem;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\WorkBreakdown;
use App\Services\ContractService;
use App\Services\WorkBreakdownService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class WorkBreakdownGenerationTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Happy path: ServiceAgreement on CleaningObject with quote_id
    // -------------------------------------------------------------------------

    public function test_signing_service_agreement_with_quote_generates_breakdown_and_tasks(): void
    {
        $user = $this->actingAsTenantUser('Admin');
        $tenant = Tenant::where('owner_id', $user->id)->first();

        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $object = CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);

        $quote = Quote::factory()->forObject($object)->create(['tenant_id' => $tenant->id]);
        QuoteItem::factory()->create([
            'tenant_id' => $tenant->id,
            'quote_id' => $quote->id,
            'name' => 'Denné upratovanie',
            'frequency' => TaskFrequencyEnum::Weekly1x->value,
            'position' => 0,
        ]);
        QuoteItem::factory()->create([
            'tenant_id' => $tenant->id,
            'quote_id' => $quote->id,
            'name' => 'Týždenné upratovanie okien',
            'frequency' => TaskFrequencyEnum::Monthly->value,
            'position' => 1,
        ]);

        $contract = Contract::factory()->create([
            'tenant_id' => $tenant->id,
            'contractable_type' => 'cleaning_object',
            'contractable_id' => $object->id,
            'category' => ContractCategoryEnum::ServiceAgreement,
            'status' => ContractStatusEnum::Draft,
            'term_type' => ContractTermTypeEnum::Fixed,
            'quote_id' => $quote->id,
            'valid_from' => now()->toDateString(),
            'end_date' => now()->addYear()->toDateString(),
        ]);

        app(ContractService::class)->sign($contract);

        $breakdown = WorkBreakdown::where('contract_id', $contract->id)->first();

        $this->assertNotNull($breakdown);
        $this->assertSame($object->id, $breakdown->cleaning_object_id);
        $this->assertSame($quote->id, $breakdown->source_quote_id);
        $this->assertTrue((bool) $breakdown->is_active);
        $this->assertCount(2, $breakdown->tasks);

        $tasks = $breakdown->tasks->sortBy('position')->values();
        $this->assertSame('Denné upratovanie', $tasks[0]->name);
        $this->assertSame(TaskFrequencyEnum::Weekly1x, $tasks[0]->frequency);
        $this->assertSame('Týždenné upratovanie okien', $tasks[1]->name);
        $this->assertSame(TaskFrequencyEnum::Monthly, $tasks[1]->frequency);
    }

    // -------------------------------------------------------------------------
    // Employment contract — no breakdown generated
    // -------------------------------------------------------------------------

    public function test_signing_employment_contract_does_not_generate_breakdown(): void
    {
        $user = $this->actingAsTenantUser('Admin');
        $tenant = Tenant::where('owner_id', $user->id)->first();

        $membership = TenantMembership::where('tenant_id', $tenant->id)->first();

        $contract = Contract::factory()->create([
            'tenant_id' => $tenant->id,
            'contractable_type' => 'tenant_membership',
            'contractable_id' => $membership->id,
            'category' => ContractCategoryEnum::Employment,
            'status' => ContractStatusEnum::Draft,
            'term_type' => ContractTermTypeEnum::Fixed,
            'quote_id' => null,
            'valid_from' => now()->toDateString(),
            'end_date' => now()->addYear()->toDateString(),
        ]);

        EmploymentContract::factory()->create([
            'contract_id' => $contract->id,
            'employment_type' => EmploymentContractTypeEnum::Dpp,
            'position' => 'Cleaner',
            'weekly_hours' => '20.00',
        ]);

        app(ContractService::class)->sign($contract);

        $this->assertDatabaseCount('work_breakdowns', 0);
    }

    // -------------------------------------------------------------------------
    // ServiceAgreement without quote_id — no breakdown
    // -------------------------------------------------------------------------

    public function test_signing_service_agreement_without_quote_does_not_generate_breakdown(): void
    {
        $user = $this->actingAsTenantUser('Admin');
        $tenant = Tenant::where('owner_id', $user->id)->first();

        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $object = CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);

        $contract = Contract::factory()->create([
            'tenant_id' => $tenant->id,
            'contractable_type' => 'cleaning_object',
            'contractable_id' => $object->id,
            'category' => ContractCategoryEnum::ServiceAgreement,
            'status' => ContractStatusEnum::Draft,
            'term_type' => ContractTermTypeEnum::Fixed,
            'quote_id' => null,
            'valid_from' => now()->toDateString(),
            'end_date' => now()->addYear()->toDateString(),
        ]);

        app(ContractService::class)->sign($contract);

        $this->assertDatabaseCount('work_breakdowns', 0);
    }

    // -------------------------------------------------------------------------
    // Idempotency — re-signing doesn't duplicate breakdown
    // -------------------------------------------------------------------------

    public function test_generate_from_contract_is_idempotent(): void
    {
        $user = $this->actingAsTenantUser('Admin');
        $tenant = Tenant::where('owner_id', $user->id)->first();

        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $object = CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);

        $quote = Quote::factory()->forObject($object)->create(['tenant_id' => $tenant->id]);
        QuoteItem::factory()->create([
            'tenant_id' => $tenant->id,
            'quote_id' => $quote->id,
            'name' => 'Task A',
            'frequency' => TaskFrequencyEnum::Weekly1x->value,
            'position' => 0,
        ]);

        $contract = Contract::factory()->create([
            'tenant_id' => $tenant->id,
            'contractable_type' => 'cleaning_object',
            'contractable_id' => $object->id,
            'category' => ContractCategoryEnum::ServiceAgreement,
            'status' => ContractStatusEnum::Draft,
            'term_type' => ContractTermTypeEnum::Fixed,
            'quote_id' => $quote->id,
            'valid_from' => now()->toDateString(),
            'end_date' => now()->addYear()->toDateString(),
        ]);

        $service = app(WorkBreakdownService::class);

        $first = $service->generateFromContract($contract);
        $second = $service->generateFromContract($contract);

        $this->assertNotNull($first);
        $this->assertNotNull($second);
        $this->assertSame($first->id, $second->id);
        $this->assertDatabaseCount('work_breakdowns', 1);
    }

    // -------------------------------------------------------------------------
    // Quote item with null frequency defaults to OneTime
    // -------------------------------------------------------------------------

    public function test_quote_item_with_null_frequency_becomes_one_time_task(): void
    {
        $user = $this->actingAsTenantUser('Admin');
        $tenant = Tenant::where('owner_id', $user->id)->first();

        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $object = CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);

        $quote = Quote::factory()->forObject($object)->create(['tenant_id' => $tenant->id]);
        QuoteItem::factory()->create([
            'tenant_id' => $tenant->id,
            'quote_id' => $quote->id,
            'name' => 'One-time deep clean',
            'frequency' => null,
            'position' => 0,
        ]);

        $contract = Contract::factory()->create([
            'tenant_id' => $tenant->id,
            'contractable_type' => 'cleaning_object',
            'contractable_id' => $object->id,
            'category' => ContractCategoryEnum::ServiceAgreement,
            'status' => ContractStatusEnum::Draft,
            'term_type' => ContractTermTypeEnum::Fixed,
            'quote_id' => $quote->id,
            'valid_from' => now()->toDateString(),
            'end_date' => now()->addYear()->toDateString(),
        ]);

        app(ContractService::class)->sign($contract);

        $breakdown = WorkBreakdown::where('contract_id', $contract->id)->firstOrFail();
        $task = $breakdown->tasks->first();

        $this->assertSame(TaskFrequencyEnum::OneTime, $task->frequency);
    }
}
