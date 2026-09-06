<?php

declare(strict_types=1);

namespace Tests\Feature\Contracts;

use App\Enums\EmploymentContractTypeEnum;
use App\Models\CleaningObject;
use App\Models\Contract;
use App\Models\EmploymentContract;
use App\Models\Tenant;
use App\Models\TenantMembership;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\View;
use Tests\TestCase;

final class ContractPdfTest extends TestCase
{
    use RefreshDatabase;

    public function test_body_is_rendered_with_line_breaks_and_html_is_escaped(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);
        $object = CleaningObject::factory()->create(['tenant_id' => $tenant->id]);
        $contract = Contract::factory()->forObject($object)->create([
            'tenant_id' => $tenant->id,
            'body' => "Prvý riadok\n<script>alert('x')</script>",
        ]);
        $contract->loadMissing('contractable');

        $html = View::make('pdf.contracts.default', ['contract' => $contract, 'tenant' => $tenant])->render();

        $this->assertStringContainsString('Prvý riadok<br', $html);
        $this->assertStringNotContainsString('<script>alert', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    public function test_employment_section_is_present_for_employment_contracts(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);
        $membership = TenantMembership::factory()->create(['tenant_id' => $tenant->id]);
        $contract = Contract::factory()->forMembership($membership)->create(['tenant_id' => $tenant->id]);
        EmploymentContract::factory()->forContract($contract)->create([
            'tenant_id' => $tenant->id,
            'employment_type' => EmploymentContractTypeEnum::Tpp,
            'position' => 'Upratovačka',
        ]);
        $contract->loadMissing(['contractable', 'employmentContract']);
        $contract->contractable->loadMissing('user');

        $html = View::make('pdf.contracts.default', ['contract' => $contract, 'tenant' => $tenant])->render();

        $this->assertStringContainsString(__('app.contract_pdf_employment_heading'), $html);
        $this->assertStringContainsString('Upratovačka', $html);
    }

    public function test_membership_party_block_renders_employee_email(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);
        $membership = TenantMembership::factory()->create(['tenant_id' => $tenant->id]);
        $contract = Contract::factory()->forMembership($membership)->create(['tenant_id' => $tenant->id]);
        $contract->loadMissing('contractable');
        $contract->contractable->loadMissing('user');

        $html = View::make('pdf.contracts.default', ['contract' => $contract, 'tenant' => $tenant])->render();

        $user = $membership->user;
        $this->assertNotNull($user);
        $this->assertStringContainsString($user->email, $html);
    }
}
