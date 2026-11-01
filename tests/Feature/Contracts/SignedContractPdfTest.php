<?php

declare(strict_types=1);

namespace Tests\Feature\Contracts;

use App\Contracts\RendersContractPdf;
use App\Events\ContractSigned;
use App\Listeners\StoreSignedContractPdf;
use App\Models\Client;
use App\Models\Contract;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Support\FakeContractPdfRenderer;
use Tests\TestCase;

final class SignedContractPdfTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake((string) config('documents.disk'));
    }

    public function test_signing_stores_the_pdf_snapshot_once(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $contract = Contract::factory()->forClient($client)->withBody('<p>Pôvodný text</p>')->create(['tenant_id' => $tenant->id]);

        $this->post(route('contracts.sign', $contract))->assertSessionHasNoErrors();

        $media = $contract->fresh()->document?->getMedia('pdf');
        $this->assertCount(1, $media ?? []);
        $renderer = app(RendersContractPdf::class);
        $this->assertInstanceOf(FakeContractPdfRenderer::class, $renderer);
        $this->assertSame(1, $renderer->freshRenders);
    }

    public function test_later_text_edits_do_not_change_the_stored_pdf(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $contract = Contract::factory()->forClient($client)->withBody('<p>Pôvodný text</p>')->create(['tenant_id' => $tenant->id]);
        $this->post(route('contracts.sign', $contract));

        $contract->fresh()->document?->update(['body' => '<p>Zmenený text</p>']);
        $client->update(['name' => 'Premenovaný klient']);

        $pdf = app(RendersContractPdf::class)->render($contract->fresh());

        $this->assertStringContainsString('Pôvodný text', $pdf);
        $this->assertStringNotContainsString('Zmenený text', $pdf);
        $this->assertSame(1, app(RendersContractPdf::class)->freshRenders);
    }

    public function test_the_snapshot_listener_is_idempotent(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $contract = Contract::factory()->forClient($client)->create(['tenant_id' => $tenant->id]);
        $this->post(route('contracts.sign', $contract));

        app(StoreSignedContractPdf::class)->handle(new ContractSigned($tenant->id, $contract->id));

        $this->assertCount(1, $contract->fresh()->document?->getMedia('pdf') ?? []);
    }

    public function test_the_listener_restores_the_previous_tenant_binding(): void
    {
        $tenant = Tenant::factory()->create();
        $other = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $contract = Contract::factory()->forClient($client)->create(['tenant_id' => $tenant->id]);
        $this->bindTenant($other);

        app(StoreSignedContractPdf::class)->handle(new ContractSigned($tenant->id, $contract->id));

        $this->assertSame($other->id, current_tenant_id());
    }

    public function test_the_listener_unbinds_the_tenant_when_none_was_bound(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $contract = Contract::factory()->forClient($client)->create(['tenant_id' => $tenant->id]);
        app()->forgetInstance('current_tenant_id');

        app(StoreSignedContractPdf::class)->handle(new ContractSigned($tenant->id, $contract->id));

        $this->assertFalse(app()->bound('current_tenant_id'));
    }
}
