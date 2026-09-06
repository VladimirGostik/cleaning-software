<?php

declare(strict_types=1);

namespace Tests\Feature\Invoices;

use App\Models\Invoice;
use App\Models\Tenant;
use App\Services\Pdf\PayBySquareService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class PayBySquareServiceTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // null cases
    // -------------------------------------------------------------------------

    public function test_data_uri_null_for_draft_invoice(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);
        $invoice = Invoice::factory()->create(['tenant_id' => $tenant->id, 'supplier_iban' => 'SK8975000000000123456789']);

        $this->assertNull(app(PayBySquareService::class)->dataUri($invoice));
    }

    public function test_data_uri_null_without_iban(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);
        $invoice = Invoice::factory()->issued()->create(['tenant_id' => $tenant->id, 'supplier_iban' => null]);

        $this->assertNull(app(PayBySquareService::class)->dataUri($invoice));
    }

    public function test_data_uri_null_for_non_eur_currency(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);
        $invoice = Invoice::factory()->issued()->create([
            'tenant_id' => $tenant->id,
            'supplier_iban' => 'SK8975000000000123456789',
            'currency' => 'USD',
        ]);

        $this->assertNull(app(PayBySquareService::class)->dataUri($invoice));
    }

    public function test_data_uri_null_when_balance_due_is_zero_or_less(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);
        $invoice = Invoice::factory()->issued()->create([
            'tenant_id' => $tenant->id,
            'supplier_iban' => 'SK8975000000000123456789',
            'total' => '100.00',
            'deposit' => '100.00',
        ]);

        $this->assertNull(app(PayBySquareService::class)->dataUri($invoice));
    }

    // -------------------------------------------------------------------------
    // non-null case
    // -------------------------------------------------------------------------

    public function test_data_uri_non_null_for_positive_eur_balance(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);
        $invoice = Invoice::factory()->issued()->create([
            'tenant_id' => $tenant->id,
            'supplier_iban' => 'SK8975000000000123456789',
            'currency' => 'EUR',
            'total' => '100.00',
            'deposit' => '0.00',
        ]);

        $dataUri = app(PayBySquareService::class)->dataUri($invoice);

        $this->assertNotNull($dataUri);
        $this->assertStringStartsWith('data:image', $dataUri);
    }
}
