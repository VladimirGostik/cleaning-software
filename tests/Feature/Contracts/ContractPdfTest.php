<?php

declare(strict_types=1);

namespace Tests\Feature\Contracts;

use App\Enums\DocumentTypeEnum;
use App\Enums\EmploymentContractTypeEnum;
use App\Models\CleaningObject;
use App\Models\Contract;
use App\Models\EmploymentContract;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Services\Documents\ContractTextService;
use App\Services\Documents\DocumentTemplateRenderer;
use App\Services\Pdf\ContractPdfService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\View;
use Tests\TestCase;

final class ContractPdfTest extends TestCase
{
    use RefreshDatabase;

    public function test_document_view_prints_the_body_and_highlights_missing_tokens(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);

        $html = View::make('pdf.documents.default', [
            'title' => 'Zmluva',
            'body' => app(DocumentTemplateRenderer::class)->highlightMissing('<p>Text {{client.dic}}</p>', DocumentTypeEnum::ServiceAgreement),
        ])->render();

        $this->assertStringContainsString('<p>Text <mark>(', $html);
        $this->assertStringNotContainsString('{{client.dic}}', $html);
    }

    public function test_employment_terms_block_is_part_of_the_displayed_text(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);
        $membership = TenantMembership::factory()->create(['tenant_id' => $tenant->id]);
        $contract = Contract::factory()->forMembership($membership)->withBody('<p>{{block.parties}}</p><p>{{block.employment_terms}}</p>')->create(['tenant_id' => $tenant->id]);
        EmploymentContract::factory()->forContract($contract)->create([
            'tenant_id' => $tenant->id,
            'employment_type' => EmploymentContractTypeEnum::Tpp,
            'position' => 'Upratovačka',
        ]);

        $html = (string) app(ContractTextService::class)->displayBody($contract->fresh());

        $this->assertStringContainsString(trans('app.contract_pdf_employment_type', [], 'sk'), $html);
        $this->assertStringContainsString('Upratovačka', $html);
    }

    public function test_membership_party_block_renders_employee_email(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);
        $membership = TenantMembership::factory()->create(['tenant_id' => $tenant->id]);
        $contract = Contract::factory()->forMembership($membership)->withBody('<p>{{block.parties}}</p>')->create(['tenant_id' => $tenant->id]);

        $html = (string) app(ContractTextService::class)->displayBody($contract->fresh());

        $this->assertNotNull($membership->user);
        $this->assertStringContainsString(e($membership->user->email), $html);
    }

    public function test_real_renderer_produces_a_pdf(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);
        $object = CleaningObject::factory()->create(['tenant_id' => $tenant->id]);
        $contract = Contract::factory()->forObject($object)->withBody('<p>Prvý riadok</p>')->create(['tenant_id' => $tenant->id]);

        $pdf = app(ContractPdfService::class)->renderFresh($contract);

        $this->assertStringStartsWith('%PDF', $pdf);
    }
}
