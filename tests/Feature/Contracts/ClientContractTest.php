<?php

declare(strict_types=1);

namespace Tests\Feature\Contracts;

use App\Enums\ContractStatusEnum;
use App\Enums\DashboardAlertTypeEnum;
use App\Models\CleaningObject;
use App\Models\Client;
use App\Models\Contract;
use App\Models\Tenant;
use App\Models\WorkBreakdown;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

final class ClientContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_contract_signs_without_work_breakdown_or_jobs(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $contract = Contract::factory()->forClient($client)->create(['tenant_id' => $tenant->id]);

        $this->post(route('contracts.sign', $contract))->assertSessionHasNoErrors();

        $this->assertSame(ContractStatusEnum::Active, $contract->fresh()->status);
        $this->assertSame(0, WorkBreakdown::query()->count());
        $this->assertDatabaseCount('cleaning_jobs', 0);
    }

    public function test_client_contract_counts_in_dashboard_metrics_and_expiring_alerts(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        Contract::factory()->forClient($client)->active()->create([
            'tenant_id' => $tenant->id,
            'end_date' => today()->addDays(10)->toDateString(),
        ]);

        /** @var array{companies: list<array{contracts: array{active: int, expiring_30d: int}|null}>, alerts: list<array{type: string}>} $overview */
        $overview = $this->get(route('dashboard'))->viewData('page')['props']['overview'];

        $this->assertSame(1, $overview['companies'][0]['contracts']['active'] ?? null);
        $this->assertSame(1, $overview['companies'][0]['contracts']['expiring_30d'] ?? null);
        $this->assertCount(1, array_filter($overview['alerts'], fn (array $alert): bool => $alert['type'] === DashboardAlertTypeEnum::ContractExpiring->value));
    }

    public function test_client_show_lists_contracts_on_the_client_and_on_its_objects(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $object = CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);
        Contract::factory()->forClient($client)->create(['tenant_id' => $tenant->id, 'title' => 'Na klienta']);
        Contract::factory()->forObject($object)->create(['tenant_id' => $tenant->id, 'title' => 'Na objekt']);
        Contract::factory()->create(['tenant_id' => $tenant->id, 'title' => 'Cudzí']);

        $this->get(route('clients.show', $client))->assertInertia(fn (AssertableInertia $page) => $page
            ->has('contracts', 2)
            ->where('canCreateContract', true));
    }

    public function test_show_pages_hide_contracts_without_view_permission(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Vedúca', $tenant);
        $object = CleaningObject::factory()->create(['tenant_id' => $tenant->id]);
        $this->assertNotNull($object);

        $this->get(route('objects.show', $object))->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->where('contracts', null)
            ->where('canCreateContract', false));
    }
}
