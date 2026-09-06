<?php

declare(strict_types=1);

namespace Tests\Feature\Tenancy;

use App\Models\Tenant;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class TenantSupplierProfileTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // happy
    // -------------------------------------------------------------------------

    public function test_complete_factory_tenant_has_no_missing_fields(): void
    {
        $tenant = Tenant::factory()->create();

        $this->assertSame([], $tenant->missingSupplierFields());
        $this->assertTrue($tenant->hasCompleteSupplierProfile());
    }

    public function test_demo_tenant_seeded_by_user_seeder_is_complete(): void
    {
        $this->seed(PermissionSeeder::class);
        $this->seed(UserSeeder::class);

        $tenant = Tenant::where('name', 'Demo Cleaning s.r.o.')->firstOrFail();

        $this->assertSame([], $tenant->missingSupplierFields());
        $this->assertTrue($tenant->is_vat_payer);
        $this->assertNotNull($tenant->iban);
    }

    // -------------------------------------------------------------------------
    // failure / edge
    // -------------------------------------------------------------------------

    public function test_non_vat_payer_missing_address_line_only(): void
    {
        $tenant = Tenant::factory()->create([
            'is_vat_payer' => false,
            'dic' => null,
            'vat_number' => null,
            'address_line' => null,
        ]);

        $this->assertSame(['address_line'], $tenant->missingSupplierFields());
        $this->assertFalse($tenant->hasCompleteSupplierProfile());
    }

    public function test_vat_payer_missing_vat_number_is_reported(): void
    {
        $tenant = Tenant::factory()->create([
            'is_vat_payer' => true,
            'vat_number' => null,
        ]);

        $this->assertContains('vat_number', $tenant->missingSupplierFields());
    }

    public function test_blank_string_field_is_treated_as_missing(): void
    {
        $tenant = Tenant::factory()->create(['city' => '']);

        $this->assertContains('city', $tenant->missingSupplierFields());
    }
}
