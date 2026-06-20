<?php

declare(strict_types=1);

namespace Tests\Feature\Contracts;

use App\Enums\ContractStatusEnum;
use App\Enums\ContractTermTypeEnum;
use App\Models\CleaningObject;
use App\Models\Client;
use App\Models\Contract;
use App\Models\Tenant;
use DateTimeInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

final class CheckContractExpiryCommandTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Phase 1 — expiry: Active + Fixed + end_date < today → Expired
    // -------------------------------------------------------------------------

    public function test_active_fixed_term_past_end_date_is_marked_expired(): void
    {
        [$contract] = $this->makeFixedContracts(1, ContractStatusEnum::Active, now()->subDay());

        $this->artisan('app:check-contract-expiry')->assertSuccessful();

        $this->assertDatabaseHas('contracts', [
            'id' => $contract->id,
            'status' => ContractStatusEnum::Expired->value,
        ]);
    }

    public function test_expired_log_is_emitted_for_flipped_contracts(): void
    {
        Log::spy();

        [$contract] = $this->makeFixedContracts(1, ContractStatusEnum::Active, now()->subDay());

        $this->artisan('app:check-contract-expiry');

        Log::shouldHaveReceived('info')
            ->once()
            ->withArgs(fn (string $msg, array $ctx) => $msg === 'contract.expired'
                && $ctx['contract_id'] === $contract->id);
    }

    // -------------------------------------------------------------------------
    // Phase 1 edge — indefinite skipped even if past end_date
    // -------------------------------------------------------------------------

    public function test_indefinite_active_contract_is_not_flipped_to_expired(): void
    {
        $tenant = Tenant::factory()->create();
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $object = CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);

        $contract = Contract::factory()->active()->create([
            'tenant_id' => $tenant->id,
            'contractable_id' => $object->id,
            'contractable_type' => 'cleaning_object',
            'term_type' => ContractTermTypeEnum::Indefinite,
            'end_date' => null,
        ]);

        $this->artisan('app:check-contract-expiry')->assertSuccessful();

        $this->assertDatabaseHas('contracts', [
            'id' => $contract->id,
            'status' => ContractStatusEnum::Active->value,
        ]);
    }

    // -------------------------------------------------------------------------
    // Phase 1 edge — idempotent: already-Expired contracts untouched
    // -------------------------------------------------------------------------

    public function test_already_expired_contract_is_not_reprocessed(): void
    {
        [$contract] = $this->makeFixedContracts(1, ContractStatusEnum::Expired, now()->subDay());

        $this->artisan('app:check-contract-expiry')->assertSuccessful();

        // Should still be Expired, not re-logged or changed
        $this->assertDatabaseHas('contracts', [
            'id' => $contract->id,
            'status' => ContractStatusEnum::Expired->value,
        ]);
    }

    // -------------------------------------------------------------------------
    // Phase 1 cross-tenant — both tenants processed
    // -------------------------------------------------------------------------

    public function test_cross_tenant_expiry_runs_for_all_tenants(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        [$contractA] = $this->makeFixedContracts(1, ContractStatusEnum::Active, now()->subDay(), $tenantA);
        [$contractB] = $this->makeFixedContracts(1, ContractStatusEnum::Active, now()->subDay(), $tenantB);

        $this->artisan('app:check-contract-expiry')->assertSuccessful();

        $this->assertDatabaseHas('contracts', ['id' => $contractA->id, 'status' => ContractStatusEnum::Expired->value]);
        $this->assertDatabaseHas('contracts', ['id' => $contractB->id, 'status' => ContractStatusEnum::Expired->value]);
    }

    // -------------------------------------------------------------------------
    // Phase 2 — approaching: log but do NOT change status
    // -------------------------------------------------------------------------

    public function test_approaching_expiry_30_days_logs_but_does_not_change_status(): void
    {
        Log::spy();

        [$contract] = $this->makeFixedContracts(1, ContractStatusEnum::Active, now()->addDays(30));

        $this->artisan('app:check-contract-expiry')->assertSuccessful();

        // Status unchanged
        $this->assertDatabaseHas('contracts', [
            'id' => $contract->id,
            'status' => ContractStatusEnum::Active->value,
        ]);

        Log::shouldHaveReceived('info')
            ->once()
            ->withArgs(fn (string $msg, array $ctx) => $msg === 'contract.expiry_approaching'
                && $ctx['days_remaining'] === 30
                && $ctx['contract_id'] === $contract->id);
    }

    public function test_approaching_expiry_14_days_logs(): void
    {
        Log::spy();

        [$contract] = $this->makeFixedContracts(1, ContractStatusEnum::Active, now()->addDays(14));

        $this->artisan('app:check-contract-expiry')->assertSuccessful();

        $this->assertDatabaseHas('contracts', [
            'id' => $contract->id,
            'status' => ContractStatusEnum::Active->value,
        ]);

        Log::shouldHaveReceived('info')
            ->once()
            ->withArgs(fn (string $msg, array $ctx) => $msg === 'contract.expiry_approaching'
                && $ctx['days_remaining'] === 14);
    }

    public function test_approaching_expiry_7_days_logs(): void
    {
        Log::spy();

        [$contract] = $this->makeFixedContracts(1, ContractStatusEnum::Active, now()->addDays(7));

        $this->artisan('app:check-contract-expiry')->assertSuccessful();

        Log::shouldHaveReceived('info')
            ->once()
            ->withArgs(fn (string $msg, array $ctx) => $msg === 'contract.expiry_approaching'
                && $ctx['days_remaining'] === 7);
    }

    // -------------------------------------------------------------------------
    // Phase 2 — today (end_date = today) is NOT expired yet, NOT approaching
    // -------------------------------------------------------------------------

    public function test_contract_expiring_today_is_not_yet_flipped(): void
    {
        [$contract] = $this->makeFixedContracts(1, ContractStatusEnum::Active, now());

        $this->artisan('app:check-contract-expiry')->assertSuccessful();

        // end_date = today, not < today → still Active
        $this->assertDatabaseHas('contracts', [
            'id' => $contract->id,
            'status' => ContractStatusEnum::Active->value,
        ]);
    }

    // -------------------------------------------------------------------------
    // Helper
    // -------------------------------------------------------------------------

    /**
     * @return array<int, Contract>
     */
    private function makeFixedContracts(
        int $count,
        ContractStatusEnum $status,
        DateTimeInterface|Carbon $endDate,
        ?Tenant $tenant = null,
    ): array {
        $tenant ??= Tenant::factory()->create();
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $object = CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);

        $contracts = [];
        for ($i = 0; $i < $count; $i++) {
            $contracts[] = Contract::factory()->create([
                'tenant_id' => $tenant->id,
                'contractable_id' => $object->id,
                'contractable_type' => 'cleaning_object',
                'status' => $status,
                'term_type' => ContractTermTypeEnum::Fixed,
                'signed_at' => $status === ContractStatusEnum::Active ? now()->subMonth() : null,
                'end_date' => Carbon::instance($endDate)->toDateString(),
            ]);
        }

        return $contracts;
    }
}
