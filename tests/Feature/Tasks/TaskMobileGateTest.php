<?php

declare(strict_types=1);

namespace Tests\Feature\Tasks;

use App\Enums\PermissionEnum;
use App\Models\Invoice;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\CreatesTaskFixtures;
use Tests\TestCase;

final class TaskMobileGateTest extends TestCase
{
    use CreatesTaskFixtures;
    use RefreshDatabase;

    private Tenant $alpha;

    private Tenant $beta;

    private User $user;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedPermissionCatalog();
        $this->alpha = $this->firm('Alpha');
        $this->beta = $this->firm('Beta');
        $this->user = $this->member($this->alpha, 'Vedúca');
        $this->member($this->beta, 'Sekretárka', $this->user);

        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId($this->alpha->id);
        $this->user->unsetRelation('roles')->unsetRelation('permissions');
        $this->user->givePermissionTo(PermissionEnum::AccessMobileApp->value);
        $registrar->setPermissionsTeamId(null);
        $this->user->unsetRelation('roles')->unsetRelation('permissions');

        $this->token = $this->user->createToken('mobile', ['mobile'])->plainTextToken;
        $this->consoleContext();
    }

    private function mobile(): static
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($this->token)->withHeaders(['X-Tenant-Id' => $this->alpha->id]);
    }

    public function test_scope_all_omits_firms_without_mobile_access(): void
    {
        $admin = $this->member($this->beta, 'Admin');
        $inAlpha = Task::factory()->forTenant($this->alpha)->createdBy($this->user)->create();
        $inBeta = Task::factory()->forTenant($this->beta)->createdBy($admin)->create();

        $ids = $this->mobile()->getJson('/api/tasks?scope=all')->assertOk()->json('data.*.id');

        $this->assertContains($inAlpha->id, (array) $ids);
        $this->assertNotContains($inBeta->id, (array) $ids);
    }

    public function test_creating_in_a_firm_without_mobile_access_is_forbidden(): void
    {
        $this->mobile()->postJson('/api/tasks', ['title' => 'X', 'priority' => 'low', 'tenant_id' => $this->beta->id])->assertForbidden();
        $this->assertSame(0, Task::unscopedForSystem()->count());
    }

    public function test_assigning_at_creation_in_a_firm_without_mobile_access_is_forbidden(): void
    {
        $target = $this->member($this->beta, 'Sekretárka');

        $this->mobile()->postJson('/api/tasks', [
            'title' => 'X', 'priority' => 'low', 'tenant_id' => $this->beta->id,
            'assigned_membership_id' => $this->membershipOf($target, $this->beta)->id,
        ])->assertForbidden();
    }

    public function test_form_context_omits_firms_without_mobile_access(): void
    {
        $response = $this->mobile()->getJson('/api/tasks/form-context')->assertOk();

        $this->assertSame([$this->alpha->id], $response->json('companies.*.tenant_id'));
        $this->assertArrayNotHasKey($this->beta->id, (array) $response->json('members'));
    }

    public function test_dashboard_omits_firms_without_mobile_access(): void
    {
        $response = $this->mobile()->getJson('/api/dashboard')->assertOk();

        $this->assertSame([$this->alpha->id], $response->json('companies.*.tenant_id'));
    }

    public function test_me_still_lists_every_company_with_its_flag(): void
    {
        $response = $this->mobile()->getJson('/api/me')->assertOk();

        $this->assertEqualsCanonicalizing([$this->alpha->id, $this->beta->id], $response->json('companies.*.tenant_id'));
    }

    public function test_scope_all_works_when_view_tasks_is_held_only_outside_the_active_firm(): void
    {
        $cleaner = $this->member($this->alpha, 'Interná upratovačka');
        $this->member($this->beta, 'Sekretárka', $cleaner);
        $admin = $this->member($this->beta, 'Admin');
        $inBeta = Task::factory()->forTenant($this->beta)->createdBy($admin)->assignedTo($this->membershipOf($cleaner, $this->beta))->create();

        $this->apiAs($cleaner, $this->alpha);

        $ids = $this->getJson('/api/tasks?scope=all')->assertOk()->json('data.*.id');
        $this->assertContains($inBeta->id, (array) $ids);
        $this->getJson('/api/tasks?scope=company')->assertForbidden();
        $this->getJson('/api/tasks/form-context')->assertOk();
    }

    public function test_form_context_is_forbidden_for_a_user_who_cannot_create_tasks_anywhere(): void
    {
        $cleaner = $this->member($this->alpha, 'Interná upratovačka');

        $this->apiAs($cleaner, $this->alpha)->getJson('/api/tasks/form-context')->assertForbidden();
    }

    public function test_scope_all_is_forbidden_for_a_user_with_no_task_view_anywhere(): void
    {
        $cleaner = $this->member($this->alpha, 'Interná upratovačka');

        $this->apiAs($cleaner, $this->alpha)->getJson('/api/tasks?scope=all')->assertForbidden();
    }

    public function test_subject_permission_is_checked_under_the_task_firm(): void
    {
        $viewer = $this->member($this->alpha, 'Sekretárka');
        $this->member($this->beta, 'Účtovníčka', $viewer);
        $invoiceInBeta = Invoice::factory()->create(['tenant_id' => $this->beta->id]);
        $invoiceInAlpha = Invoice::factory()->create(['tenant_id' => $this->alpha->id]);

        $this->apiAs($viewer, $this->alpha)
            ->postJson('/api/tasks', ['title' => 'In B', 'priority' => 'low', 'tenant_id' => $this->beta->id, 'taskable_type' => 'invoice', 'taskable_id' => $invoiceInBeta->id])
            ->assertCreated();
        $this->apiAs($viewer, $this->alpha)
            ->postJson('/api/tasks', ['title' => 'In A', 'priority' => 'low', 'tenant_id' => $this->alpha->id, 'taskable_type' => 'invoice', 'taskable_id' => $invoiceInAlpha->id])
            ->assertForbidden();

        $accountant = $this->member($this->alpha, 'Účtovníčka');
        $this->member($this->beta, 'Sekretárka', $accountant);
        $this->apiAs($accountant, $this->alpha)
            ->postJson('/api/tasks', ['title' => 'Denied in B', 'priority' => 'low', 'tenant_id' => $this->beta->id, 'taskable_type' => 'invoice', 'taskable_id' => $invoiceInBeta->id])
            ->assertForbidden();
        $this->assertSame($this->alpha->id, app('current_tenant_id'));
    }
}
