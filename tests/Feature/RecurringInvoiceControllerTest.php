<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\InvoiceTypeEnum;
use App\Enums\RecurringFrequencyEnum;
use App\Enums\RecurringInvoiceStatusEnum;
use App\Models\RecurringInvoice;
use App\Models\RecurringInvoiceItem;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleTemplatesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

final class RecurringInvoiceControllerTest extends TestCase
{
    use RefreshDatabase;

    private function makeTemplate(User $user, array $overrides = []): RecurringInvoice
    {
        $tenantId = app('current_tenant_id');

        $ri = RecurringInvoice::factory()->create(array_merge([
            'tenant_id' => $tenantId,
            'type' => InvoiceTypeEnum::Monthly,
            'frequency' => RecurringFrequencyEnum::Monthly,
            'day_of_month' => 15,
            'status' => RecurringInvoiceStatusEnum::Active,
            'auto_issue' => false,
            'start_date' => now()->subMonth()->toDateString(),
            'next_run_at' => now()->addMonth()->toDateString(),
            'customer_name' => 'Test Customer',
            'due_days' => 14,
        ], $overrides));

        RecurringInvoiceItem::factory()->create([
            'tenant_id' => $tenantId,
            'recurring_invoice_id' => $ri->id,
        ]);

        return $ri;
    }

    private function storePayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Monthly Test',
            'type' => InvoiceTypeEnum::Monthly->value,
            'frequency' => RecurringFrequencyEnum::Monthly->value,
            'day_of_month' => 15,
            'auto_issue' => false,
            'start_date' => now()->subMonth()->toDateString(),
            'due_days' => 14,
            'customer_name' => 'Acme s.r.o.',
            'period_from' => now()->startOfMonth()->toDateString(),
            'period_to' => now()->endOfMonth()->toDateString(),
            'items' => [
                ['description' => 'Cleaning', 'quantity' => 1, 'unit_price' => 100],
            ],
        ], $overrides);
    }

    // -------------------------------------------------------------------------
    // Happy path
    // -------------------------------------------------------------------------

    public function test_index_returns_200_for_authorized_user(): void
    {
        $user = $this->actingAsTenantUser('Admin');

        $this->get(route('recurring-invoices.index'))->assertOk();
    }

    public function test_create_returns_200_for_authorized_user(): void
    {
        $user = $this->actingAsTenantUser('Admin');

        $this->get(route('recurring-invoices.create'))->assertOk();
    }

    public function test_store_creates_recurring_invoice_and_redirects(): void
    {
        $user = $this->actingAsTenantUser('Admin');

        $response = $this->post(route('recurring-invoices.store'), $this->storePayload());

        $response->assertRedirect();
        $this->assertDatabaseHas('recurring_invoices', ['name' => 'Monthly Test']);
    }

    public function test_show_returns_200_for_authorized_user(): void
    {
        $user = $this->actingAsTenantUser('Admin');
        $ri = $this->makeTemplate($user);

        $this->get(route('recurring-invoices.show', $ri))->assertOk();
    }

    public function test_pause_sets_status_to_paused(): void
    {
        $user = $this->actingAsTenantUser('Admin');
        $ri = $this->makeTemplate($user);

        $this->post(route('recurring-invoices.pause', $ri))->assertRedirect();

        $this->assertSame(RecurringInvoiceStatusEnum::Paused, $ri->fresh()->status);
    }

    public function test_resume_sets_status_to_active(): void
    {
        $user = $this->actingAsTenantUser('Admin');
        $ri = $this->makeTemplate($user);
        $ri->update(['status' => RecurringInvoiceStatusEnum::Paused, 'next_run_at' => null]);

        $this->post(route('recurring-invoices.resume', $ri))->assertRedirect();

        $this->assertSame(RecurringInvoiceStatusEnum::Active, $ri->fresh()->status);
    }

    public function test_cancel_sets_status_to_cancelled(): void
    {
        $user = $this->actingAsTenantUser('Admin');
        $ri = $this->makeTemplate($user);

        $this->post(route('recurring-invoices.cancel', $ri))->assertRedirect();

        $this->assertSame(RecurringInvoiceStatusEnum::Cancelled, $ri->fresh()->status);
    }

    public function test_destroy_soft_deletes(): void
    {
        $user = $this->actingAsTenantUser('Admin');
        $ri = $this->makeTemplate($user);

        $this->delete(route('recurring-invoices.destroy', $ri))->assertRedirect();

        $this->assertSoftDeleted('recurring_invoices', ['id' => $ri->id]);
    }

    // -------------------------------------------------------------------------
    // Auth / permission denial — 403
    // -------------------------------------------------------------------------

    public function test_upratovacka_cannot_view_index(): void
    {
        $user = $this->actingAsTenantUser('Interná upratovačka');

        $this->get(route('recurring-invoices.index'))->assertForbidden();
    }

    public function test_upratovacka_cannot_create(): void
    {
        $user = $this->actingAsTenantUser('Interná upratovačka');

        $this->post(route('recurring-invoices.store'), $this->storePayload())->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // Tenant isolation
    // -------------------------------------------------------------------------

    public function test_cannot_view_other_tenant_recurring_invoice(): void
    {
        // Create template as user in tenant A
        $userA = $this->actingAsTenantUser('Admin');
        $ri = $this->makeTemplate($userA);

        // Log in as user in tenant B
        $userB = User::factory()->create(['is_active' => true]);
        $tenantB = Tenant::factory()->forOwner($userB)->create();
        app(PermissionRegistrar::class)->setPermissionsTeamId($tenantB->id);
        $this->seed(PermissionSeeder::class);
        app(PermissionRegistrar::class)->setPermissionsTeamId($tenantB->id);
        $this->seed(RoleTemplatesSeeder::class);
        app(PermissionRegistrar::class)->setPermissionsTeamId($tenantB->id);

        $this->actingAs($userB);
        session(['active_tenant_id' => $tenantB->id]);
        app()->instance('current_tenant_id', $tenantB->id);

        // Admin role in tenant B
        $role = Role::where('name', 'Admin')->where('tenant_id', $tenantB->id)->firstOrFail();
        $userB->assignRole($role);
        TenantMembership::create([
            'user_id' => $userB->id,
            'tenant_id' => $tenantB->id,
            'is_active' => true,
            'joined_at' => now(),
        ]);

        // Template belongs to tenant A, user B is in tenant B — should 404 (TenantScope hides it)
        $this->get(route('recurring-invoices.show', $ri))->assertNotFound();
    }
}
