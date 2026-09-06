<?php

declare(strict_types=1);

namespace Tests\Feature\Contracts;

use App\Enums\ContractStatusEnum;
use App\Events\ContractExpired;
use App\Events\ContractExpiring;
use App\Models\Contract;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

final class CheckContractExpiryCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_fixed_term_past_end_date_becomes_expired_and_dispatches_event(): void
    {
        Event::fake([ContractExpired::class]);
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);
        $contract = Contract::factory()->active()->create(['tenant_id' => $tenant->id, 'end_date' => now()->subDay()->toDateString()]);

        $this->artisan('app:check-contract-expiry')->assertExitCode(0);

        $contract->refresh();
        $this->assertSame(ContractStatusEnum::Expired, $contract->status);
        Event::assertDispatched(ContractExpired::class, fn (ContractExpired $e) => $e->contractId === $contract->id);
    }

    public function test_expiry_is_logged(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);
        $contract = Contract::factory()->active()->create(['tenant_id' => $tenant->id, 'end_date' => now()->subDay()->toDateString()]);

        $this->artisan('app:check-contract-expiry');

        $contract->refresh();
        $this->assertSame(ContractStatusEnum::Expired, $contract->status);
    }

    public function test_indefinite_contract_is_untouched(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);
        $contract = Contract::factory()->active()->indefinite()->create(['tenant_id' => $tenant->id]);

        $this->artisan('app:check-contract-expiry');

        $contract->refresh();
        $this->assertSame(ContractStatusEnum::Active, $contract->status);
    }

    public function test_end_date_today_is_not_expired(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);
        $contract = Contract::factory()->active()->create(['tenant_id' => $tenant->id, 'end_date' => now()->toDateString()]);

        $this->artisan('app:check-contract-expiry');

        $contract->refresh();
        $this->assertSame(ContractStatusEnum::Active, $contract->status);
    }

    public function test_already_expired_contract_is_skipped(): void
    {
        Event::fake([ContractExpired::class]);
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);
        Contract::factory()->expired()->create(['tenant_id' => $tenant->id]);

        $this->artisan('app:check-contract-expiry');

        Event::assertNotDispatched(ContractExpired::class);
    }

    public function test_draft_contract_is_untouched(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);
        $contract = Contract::factory()->draft()->create(['tenant_id' => $tenant->id, 'end_date' => now()->subDay()->toDateString()]);

        $this->artisan('app:check-contract-expiry');

        $contract->refresh();
        $this->assertSame(ContractStatusEnum::Draft, $contract->status);
    }

    public function test_expires_across_tenants(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $this->bindTenant($tenantA);
        $contractA = Contract::factory()->active()->create(['tenant_id' => $tenantA->id, 'end_date' => now()->subDay()->toDateString()]);
        $this->bindTenant($tenantB);
        $contractB = Contract::factory()->active()->create(['tenant_id' => $tenantB->id, 'end_date' => now()->subDay()->toDateString()]);

        $this->artisan('app:check-contract-expiry');

        $contractA->refresh();
        $contractB->refresh();
        $this->assertSame(ContractStatusEnum::Expired, $contractA->status);
        $this->assertSame(ContractStatusEnum::Expired, $contractB->status);
    }

    public function test_dispatches_expiring_event_for_configured_notice_days(): void
    {
        Event::fake([ContractExpiring::class]);
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);
        $contract = Contract::factory()->active()->create(['tenant_id' => $tenant->id, 'end_date' => now()->addDays(30)->toDateString()]);

        $this->artisan('app:check-contract-expiry');

        Event::assertDispatched(ContractExpiring::class, fn (ContractExpiring $e) => $e->contractId === $contract->id && $e->daysLeft === 30);
    }

    public function test_expiring_today_is_not_flipped_to_expired(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);
        $contract = Contract::factory()->active()->create(['tenant_id' => $tenant->id, 'end_date' => now()->toDateString()]);

        $this->artisan('app:check-contract-expiry');

        $contract->refresh();
        $this->assertSame(ContractStatusEnum::Active, $contract->status);
    }
}
