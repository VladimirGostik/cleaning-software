<?php

declare(strict_types=1);

namespace Tests\Feature\Contracts;

use App\Data\ContractTemplates\ContractTemplateIndexFilterData;
use App\Data\ContractTemplates\ContractTemplateStoreData;
use App\Enums\ContractCategoryEnum;
use App\Models\CleaningObject;
use App\Models\Client;
use App\Models\ContractTemplate;
use App\Models\Tenant;
use App\Services\ContractTemplateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ContractTemplateServiceTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // create
    // -------------------------------------------------------------------------

    public function test_create_persists_template_with_correct_fields(): void
    {
        $user = $this->actingAsTenantUser('Admin');
        $tenant = Tenant::where('owner_id', $user->id)->first();

        app()->instance('current_tenant_id', $tenant->id);

        $service = app(ContractTemplateService::class);
        $data = ContractTemplateStoreData::from([
            'name' => 'Cleaning Agreement Template',
            'category' => ContractCategoryEnum::ServiceAgreement->value,
            'body' => 'Contract between {{tenant.name}} and client {{client.name}}.',
            'is_active' => true,
        ]);

        $template = $service->create($data);

        $this->assertDatabaseHas('contract_templates', [
            'id' => $template->id,
            'tenant_id' => $tenant->id,
            'name' => 'Cleaning Agreement Template',
            'category' => 'service_agreement',
            'is_active' => true,
        ]);
    }

    public function test_create_sets_is_active_false(): void
    {
        $user = $this->actingAsTenantUser('Admin');
        $tenant = Tenant::where('owner_id', $user->id)->first();

        app()->instance('current_tenant_id', $tenant->id);

        $service = app(ContractTemplateService::class);
        $data = ContractTemplateStoreData::from([
            'name' => 'Inactive template',
            'category' => ContractCategoryEnum::Nda->value,
            'body' => 'NDA body',
            'is_active' => false,
        ]);

        $template = $service->create($data);

        $this->assertFalse($template->is_active);
    }

    // -------------------------------------------------------------------------
    // update
    // -------------------------------------------------------------------------

    public function test_update_modifies_existing_template(): void
    {
        $user = $this->actingAsTenantUser('Admin');
        $tenant = Tenant::where('owner_id', $user->id)->first();

        $template = ContractTemplate::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Old name',
            'category' => ContractCategoryEnum::ServiceAgreement,
            'is_active' => true,
        ]);

        $service = app(ContractTemplateService::class);
        $data = ContractTemplateStoreData::from([
            'name' => 'Updated name',
            'category' => ContractCategoryEnum::Gdpr->value,
            'body' => 'GDPR body updated',
            'is_active' => false,
        ]);

        $updated = $service->update($template, $data);

        $this->assertSame('Updated name', $updated->name);
        $this->assertSame(ContractCategoryEnum::Gdpr, $updated->category);
        $this->assertFalse($updated->is_active);
    }

    // -------------------------------------------------------------------------
    // delete (soft delete)
    // -------------------------------------------------------------------------

    public function test_delete_soft_deletes_template(): void
    {
        $user = $this->actingAsTenantUser('Admin');
        $tenant = Tenant::where('owner_id', $user->id)->first();

        $template = ContractTemplate::factory()->create(['tenant_id' => $tenant->id]);

        $service = app(ContractTemplateService::class);
        $service->delete($template);

        $this->assertSoftDeleted('contract_templates', ['id' => $template->id]);
        $this->assertNotNull($template->fresh()?->deleted_at ?? $template->deleted_at);
    }

    public function test_deleted_template_excluded_from_paginate(): void
    {
        $user = $this->actingAsTenantUser('Admin');
        $tenant = Tenant::where('owner_id', $user->id)->first();

        $active = ContractTemplate::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Active']);
        $deleted = ContractTemplate::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Deleted']);
        $deleted->delete();

        $service = app(ContractTemplateService::class);
        $paginator = $service->paginate(ContractTemplateIndexFilterData::from([]));

        $ids = collect($paginator->items())->pluck('id')->all();
        $this->assertContains($active->id, $ids);
        $this->assertNotContains($deleted->id, $ids);
    }

    // -------------------------------------------------------------------------
    // soft-deleted template excluded from Rule::exists in DTO
    // -------------------------------------------------------------------------

    public function test_soft_deleted_template_id_fails_contract_store_validation(): void
    {
        $user = $this->actingAsTenantUser('Admin');
        $tenant = Tenant::where('owner_id', $user->id)->first();

        $template = ContractTemplate::factory()->create(['tenant_id' => $tenant->id]);
        $template->delete();

        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $object = CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);

        $response = $this->post(route('contracts.store'), [
            'title' => 'Test contract',
            'category' => 'service_agreement',
            'term_type' => 'fixed',
            'contractable_type' => 'cleaning_object',
            'contractable_id' => $object->id,
            'contract_template_id' => $template->id,
            'body' => 'body',
            'valid_from' => now()->toDateString(),
            'end_date' => now()->addYear()->toDateString(),
        ]);

        $response->assertSessionHasErrors(['contract_template_id']);
    }
}
