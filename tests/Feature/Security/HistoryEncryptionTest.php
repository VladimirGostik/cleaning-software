<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Models\Client;
use App\Models\Contract;
use App\Models\Tenant;
use App\Models\TenantMembership;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class HistoryEncryptionTest extends TestCase
{
    use RefreshDatabase;

    private function encrypted(string $url): bool
    {
        $page = $this->get($url)->assertOk()->viewData('page');

        return is_array($page) && ($page['encryptHistory'] ?? false) === true;
    }

    public function test_sensitive_pages_encrypt_the_browser_history(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $contract = Contract::factory()->forClient($client)->draft()->create(['tenant_id' => $tenant->id]);
        $membership = TenantMembership::factory()->create(['tenant_id' => $tenant->id]);

        $this->assertTrue($this->encrypted(route('contracts.show', $contract)));
        $this->assertTrue($this->encrypted(route('contracts.edit', $contract)));
        $this->assertTrue($this->encrypted(route('employees.edit', $membership)));
    }

    public function test_other_pages_do_not_encrypt_the_history(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);

        $this->assertFalse($this->encrypted(route('contracts.index')));
    }

    public function test_logout_clears_the_browser_history(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);

        $this->post(route('logout'))->assertRedirect(route('login'))->assertSessionHas('inertia.clear_history', true);
    }
}
