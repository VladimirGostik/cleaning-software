<?php

declare(strict_types=1);

namespace Tests\Feature\Contracts;

use App\Models\Quote;
use App\Models\Tenant;
use App\Policies\QuotePolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class QuoteConvertToContractPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_convert_to_contract_allowed_with_create_contracts_permission(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->actingAsTenantUser('Admin', $tenant);
        $quote = Quote::factory()->accepted()->create(['tenant_id' => $tenant->id]);

        $this->assertTrue((new QuotePolicy)->convertToContract($user, $quote));
    }

    public function test_convert_to_contract_denied_without_create_contracts_permission(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->actingAsTenantUser('Účtovníčka', $tenant);
        $quote = Quote::factory()->accepted()->create(['tenant_id' => $tenant->id]);

        $this->assertFalse((new QuotePolicy)->convertToContract($user, $quote));
    }
}
