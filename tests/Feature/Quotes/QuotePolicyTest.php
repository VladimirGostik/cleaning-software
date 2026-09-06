<?php

declare(strict_types=1);

namespace Tests\Feature\Quotes;

use App\Models\Quote;
use App\Models\Tenant;
use App\Policies\QuotePolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class QuotePolicyTest extends TestCase
{
    use RefreshDatabase;

    private QuotePolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new QuotePolicy;
    }

    public function test_admin_can_do_everything(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->actingAsTenantUser('Admin', $tenant);
        $quote = Quote::factory()->create(['tenant_id' => $tenant->id]);

        $this->assertTrue($this->policy->viewAny($user));
        $this->assertTrue($this->policy->view($user, $quote));
        $this->assertTrue($this->policy->create($user));
        $this->assertTrue($this->policy->update($user, $quote));
        $this->assertTrue($this->policy->attachClient($user, $quote));
        $this->assertTrue($this->policy->delete($user, $quote));
        $this->assertTrue($this->policy->send($user, $quote));
        $this->assertTrue($this->policy->accept($user, $quote));
        $this->assertTrue($this->policy->reject($user, $quote));
        $this->assertTrue($this->policy->duplicate($user, $quote));
        $this->assertTrue($this->policy->convertToInvoice($user, $quote));
        $this->assertTrue($this->policy->downloadPdf($user, $quote));
    }

    public function test_secretary_can_manage_quotes_but_not_convert_to_invoice(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->actingAsTenantUser('Sekretárka', $tenant);
        $quote = Quote::factory()->create(['tenant_id' => $tenant->id]);

        $this->assertTrue($this->policy->viewAny($user));
        $this->assertTrue($this->policy->create($user));
        $this->assertTrue($this->policy->update($user, $quote));
        $this->assertTrue($this->policy->delete($user, $quote));
        $this->assertTrue($this->policy->send($user, $quote));
        $this->assertTrue($this->policy->accept($user, $quote));
        $this->assertFalse($this->policy->convertToInvoice($user, $quote));
    }

    public function test_vedouca_can_only_view(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->actingAsTenantUser('Vedúca', $tenant);
        $quote = Quote::factory()->create(['tenant_id' => $tenant->id]);

        $this->assertTrue($this->policy->viewAny($user));
        $this->assertTrue($this->policy->view($user, $quote));
        $this->assertFalse($this->policy->create($user));
        $this->assertFalse($this->policy->update($user, $quote));
        $this->assertFalse($this->policy->delete($user, $quote));
    }

    public function test_accountant_is_view_only(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->actingAsTenantUser('Účtovníčka', $tenant);
        $quote = Quote::factory()->create(['tenant_id' => $tenant->id]);

        $this->assertTrue($this->policy->viewAny($user));
        $this->assertTrue($this->policy->view($user, $quote));
        $this->assertFalse($this->policy->create($user));
        $this->assertFalse($this->policy->update($user, $quote));
        $this->assertFalse($this->policy->send($user, $quote));
        $this->assertFalse($this->policy->accept($user, $quote));
        $this->assertFalse($this->policy->delete($user, $quote));
    }

    public function test_cleaner_has_no_quote_access(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->actingAsTenantUser('Interná upratovačka', $tenant);
        $quote = Quote::factory()->create(['tenant_id' => $tenant->id]);

        $this->assertFalse($this->policy->viewAny($user));
        $this->assertFalse($this->policy->view($user, $quote));
        $this->assertFalse($this->policy->create($user));
    }
}
