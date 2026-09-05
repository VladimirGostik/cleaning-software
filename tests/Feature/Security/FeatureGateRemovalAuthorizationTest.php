<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Models\CleaningObject;
use App\Models\Client;
use App\Models\Contract;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Proves RBAC alone still gates every ex-`feature:*` route now that the
 * entitlement/subscription axis is removed. See
 * .claude/plans/cleaner-actor-scoping.md — SECURITY GATE + Test scenarios.
 */
final class FeatureGateRemovalAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // happy: role holding the view permission gets 200 on the ex-gated index
    // -------------------------------------------------------------------------

    public function test_sekretarka_can_view_objects_and_quotes(): void
    {
        $this->actingAsTenantUser('Sekretárka');

        $this->get(route('objects.index'))->assertOk();
        $this->get(route('quotes.index'))->assertOk();
    }

    public function test_uctovnicka_can_view_invoices_recurring_invoices_and_settings(): void
    {
        $this->actingAsTenantUser('Účtovníčka');

        $this->get(route('invoices.index'))->assertOk();
        $this->get(route('recurring-invoices.index'))->assertOk();
        $this->get(route('settings.invoicing'))->assertOk();
    }

    public function test_sekretarka_and_uctovnicka_can_view_contracts_and_templates(): void
    {
        $this->actingAsTenantUser('Sekretárka');
        $this->get(route('contracts.index'))->assertOk();
        $this->get(route('contract-templates.index'))->assertOk();

        $this->actingAsTenantUser('Účtovníčka');
        $this->get(route('contracts.index'))->assertOk();
        $this->get(route('contract-templates.index'))->assertOk();
    }

    public function test_veduca_can_view_employees_and_jobs(): void
    {
        $this->actingAsTenantUser('Vedúca');

        $this->get(route('employees.index'))->assertOk();
        $this->get(route('jobs.index'))->assertOk();
    }

    // -------------------------------------------------------------------------
    // failure: role lacking the permission gets 403 on every ex-gated index —
    // the core proof that RBAC alone still denies, now that nothing else does.
    // -------------------------------------------------------------------------

    public function test_upratovacka_is_forbidden_on_every_ex_gated_index_except_objects_and_jobs(): void
    {
        $this->actingAsTenantUser('Interná upratovačka');

        $this->get(route('quotes.index'))->assertForbidden();
        $this->get(route('invoices.index'))->assertForbidden();
        $this->get(route('recurring-invoices.index'))->assertForbidden();
        $this->get(route('contracts.index'))->assertForbidden();
        $this->get(route('contract-templates.index'))->assertForbidden();
        $this->get(route('employees.index'))->assertForbidden();
        $this->get(route('settings.invoicing'))->assertForbidden();
    }

    public function test_zakaznik_is_forbidden_on_every_ex_gated_index_except_objects_and_jobs(): void
    {
        $this->actingAsTenantUser('Zákazník');

        $this->get(route('quotes.index'))->assertForbidden();
        $this->get(route('invoices.index'))->assertForbidden();
        $this->get(route('recurring-invoices.index'))->assertForbidden();
        $this->get(route('contracts.index'))->assertForbidden();
        $this->get(route('contract-templates.index'))->assertForbidden();
        $this->get(route('employees.index'))->assertForbidden();
        $this->get(route('settings.invoicing'))->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // edge: write actions stay denied for read-only permission holders
    // -------------------------------------------------------------------------

    public function test_veduca_forbidden_on_create_employees_despite_view_permission(): void
    {
        $this->actingAsTenantUser('Vedúca');

        $this->post(route('employees.store'), [])->assertForbidden();
    }

    public function test_uctovnicka_forbidden_on_edit_contract_despite_view_permission(): void
    {
        $user = $this->actingAsTenantUser('Účtovníčka');
        $tenant = Tenant::where('owner_id', $user->id)->first();

        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $object = CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);
        $contract = Contract::factory()->create([
            'tenant_id' => $tenant->id,
            'contractable_id' => $object->id,
            'contractable_type' => 'cleaning_object',
        ]);

        $this->put(route('contracts.update', $contract), [])->assertForbidden();
    }

    public function test_upratovacka_forbidden_on_create_job_despite_view_permission(): void
    {
        $this->actingAsTenantUser('Interná upratovačka');

        $this->post(route('jobs.store'), [])->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // W1 widening resolved via actor scoping (.claude/plans/cleaner-actor-scoping.md):
    // own-only roles still reach the index routes (RBAC permission unchanged), but the
    // list is now scoped to rows reachable through the actor's own membership. Zákazník
    // has no TenantMembership assignments on any job, so both indexes render zero rows.
    // Cleaner (own-only, with assigned jobs) assertions live in CleanerActorScopingTest.
    // -------------------------------------------------------------------------

    public function test_zakaznik_reaches_objects_and_jobs_index_with_zero_rows_own_only(): void
    {
        $this->actingAsTenantUser('Zákazník');

        $this->get(route('objects.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->has('objects.data', 0));

        $this->get(route('jobs.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->has('jobs.data', 0));
    }
}
