<?php

declare(strict_types=1);

namespace Tests\Feature\Contracts;

use App\Data\ContractTemplates\ContractTemplateUpsertData;
use App\Enums\ContractCategoryEnum;
use App\Models\ContractTemplate;
use App\Models\Tenant;
use App\Services\ContractTemplateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

final class ContractTemplateServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_template(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);

        $template = app(ContractTemplateService::class)->create(new ContractTemplateUpsertData(
            name: 'Zmluva o upratovaní',
            category: ContractCategoryEnum::ServiceAgreement,
            body: 'Text zmluvy {{client.name}}',
        ));

        $this->assertDatabaseHas('contract_templates', [
            'id' => $template->id,
            'tenant_id' => $tenant->id,
            'name' => 'Zmluva o upratovaní',
        ]);
    }

    public function test_updates_template(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);
        $template = ContractTemplate::factory()->create(['tenant_id' => $tenant->id]);

        app(ContractTemplateService::class)->update($template, new ContractTemplateUpsertData(
            name: 'Nový názov',
            category: ContractCategoryEnum::Employment,
            body: 'Nový text',
            is_active: false,
        ));

        $template->refresh();
        $this->assertSame('Nový názov', $template->name);
        $this->assertSame(ContractCategoryEnum::Employment, $template->category);
        $this->assertFalse($template->is_active);
    }

    public function test_deletes_template_soft(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);
        $template = ContractTemplate::factory()->create(['tenant_id' => $tenant->id]);

        app(ContractTemplateService::class)->delete($template);

        $this->assertSoftDeleted('contract_templates', ['id' => $template->id]);
    }

    public function test_paginate_search_matches_name(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);
        ContractTemplate::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Upratovacia zmluva']);
        ContractTemplate::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Iná šablóna']);

        $request = Request::create('/', 'GET', ['filter' => ['search' => 'Upratovacia']]);
        app()->instance('request', $request);

        $result = app(ContractTemplateService::class)->paginate($request);

        $this->assertSame(1, $result->total());
    }

    public function test_paginate_filters_by_category(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);
        ContractTemplate::factory()->create(['tenant_id' => $tenant->id, 'category' => ContractCategoryEnum::ServiceAgreement]);
        ContractTemplate::factory()->employment()->create(['tenant_id' => $tenant->id]);

        $request = Request::create('/', 'GET', ['filter' => ['category' => 'employment']]);
        app()->instance('request', $request);

        $result = app(ContractTemplateService::class)->paginate($request);

        $this->assertSame(1, $result->total());
    }

    public function test_active_scope_excludes_inactive_templates(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);
        ContractTemplate::factory()->create(['tenant_id' => $tenant->id]);
        ContractTemplate::factory()->inactive()->create(['tenant_id' => $tenant->id]);

        $this->assertSame(1, ContractTemplate::query()->active()->count());
    }

    public function test_cross_tenant_templates_are_invisible(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        ContractTemplate::factory()->create(['tenant_id' => $tenantB->id]);
        $this->bindTenant($tenantA);

        $result = app(ContractTemplateService::class)->paginate(Request::create('/'));

        $this->assertSame(0, $result->total());
    }
}
