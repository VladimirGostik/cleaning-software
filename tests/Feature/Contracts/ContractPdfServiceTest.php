<?php

declare(strict_types=1);

namespace Tests\Feature\Contracts;

use App\Enums\ContractCategoryEnum;
use App\Models\CleaningObject;
use App\Models\Client;
use App\Models\Contract;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use App\Services\Pdf\ContractPdfService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ContractPdfServiceTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // eagerLoadForRender — tenant_membership contractable
    // -------------------------------------------------------------------------

    public function test_eager_load_for_render_loads_user_for_tenant_membership_contractable(): void
    {
        $owner = User::factory()->create();
        $tenant = Tenant::factory()->forOwner($owner)->create();
        $membership = TenantMembership::create([
            'user_id' => $owner->id,
            'tenant_id' => $tenant->id,
            'is_active' => true,
            'joined_at' => now(),
        ]);

        $contract = Contract::factory()->create([
            'tenant_id' => $tenant->id,
            'contractable_type' => 'tenant_membership',
            'contractable_id' => $membership->id,
            'category' => ContractCategoryEnum::Employment,
        ]);

        // Discard all in-memory relations to simulate a freshly resolved model.
        $contract = $contract->fresh();
        $this->assertNotNull($contract);

        $service = app(ContractPdfService::class);
        $loaded = $service->eagerLoadForRender($contract);

        $this->assertTrue($loaded->relationLoaded('contractable'));
        $this->assertTrue($loaded->relationLoaded('employmentContract'));
        $this->assertTrue($loaded->relationLoaded('contractTemplate'));

        $this->assertInstanceOf(TenantMembership::class, $loaded->contractable);
        // Critical: user must be eagerly loaded — no LazyLoadingViolationException
        $this->assertTrue($loaded->contractable->relationLoaded('user'));
    }

    // -------------------------------------------------------------------------
    // eagerLoadForRender — cleaning_object contractable (no user relation)
    // -------------------------------------------------------------------------

    public function test_eager_load_for_render_does_not_trigger_violation_for_cleaning_object_contractable(): void
    {
        $owner = User::factory()->create();
        $tenant = Tenant::factory()->forOwner($owner)->create();
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $object = CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);

        $contract = Contract::factory()->create([
            'tenant_id' => $tenant->id,
            'contractable_type' => 'cleaning_object',
            'contractable_id' => $object->id,
        ]);

        $contract = $contract->fresh();
        $this->assertNotNull($contract);

        $service = app(ContractPdfService::class);
        // Must not throw LazyLoadingViolationException
        $loaded = $service->eagerLoadForRender($contract);

        $this->assertTrue($loaded->relationLoaded('contractable'));
        $this->assertInstanceOf(CleaningObject::class, $loaded->contractable);
    }
}
