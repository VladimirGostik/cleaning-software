<?php

declare(strict_types=1);

namespace Tests\Feature\Tasks;

use App\Models\Task;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesTaskFixtures;
use Tests\TestCase;

final class TaskApiTest extends TestCase
{
    use CreatesTaskFixtures;
    use RefreshDatabase;

    private Tenant $alpha;

    private Tenant $beta;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedPermissionCatalog();
        $this->alpha = $this->firm('Alpha');
        $this->beta = $this->firm('Beta');
        $this->user = $this->member($this->alpha, 'Sekretárka');
        $this->member($this->beta, 'Vedúca', $this->user);
    }

    public function test_company_scope_lists_the_active_firm_only_and_all_scope_spans_firms(): void
    {
        $other = $this->member($this->alpha, 'Admin');
        $this->member($this->beta, 'Admin', $other);
        $inAlpha = Task::factory()->forTenant($this->alpha)->createdBy($this->user)->create();
        $inBeta = Task::factory()->forTenant($this->beta)->createdBy($other)->create();

        /** @var list<string> $company */
        $company = $this->apiAs($this->user, $this->alpha)->getJson('/api/tasks?scope=company')->assertOk()->json('data.*.id');
        $this->assertSame([$inAlpha->id], $company);

        /** @var list<string> $all */
        $all = $this->apiAs($this->user, $this->alpha)->getJson('/api/tasks?scope=all')->assertOk()->json('data.*.id');
        $this->assertEqualsCanonicalizing([$inAlpha->id, $inBeta->id], $all);
    }

    public function test_all_scope_is_own_only_in_firms_without_view_all_tasks(): void
    {
        $admin = $this->member($this->alpha, 'Admin');
        $this->member($this->beta, 'Admin', $admin);
        $hiddenInAlpha = Task::factory()->forTenant($this->alpha)->createdBy($admin)->create();
        $mineInAlpha = Task::factory()->forTenant($this->alpha)->createdBy($this->user)->create();
        $assignedInAlpha = Task::factory()->assignedTo($this->membershipOf($this->user, $this->alpha))->createdBy($admin)->create();
        $visibleInBeta = Task::factory()->forTenant($this->beta)->createdBy($admin)->create();

        /** @var list<string> $ids */
        $ids = $this->apiAs($this->user, $this->alpha)->getJson('/api/tasks?scope=all')->assertOk()->json('data.*.id');

        $this->assertEqualsCanonicalizing([$mineInAlpha->id, $assignedInAlpha->id, $visibleInBeta->id], $ids);
        $this->assertNotContains($hiddenInAlpha->id, $ids);
    }

    public function test_all_scope_excludes_firms_where_the_user_cannot_view_tasks(): void
    {
        $gamma = $this->firm('Gamma');
        $this->member($gamma, 'Interná upratovačka', $this->user);
        $admin = $this->member($gamma, 'Admin');
        $task = Task::factory()->forTenant($gamma)->createdBy($admin)->create();

        /** @var list<string> $ids */
        $ids = $this->apiAs($this->user, $this->alpha)->getJson('/api/tasks?scope=all')->assertOk()->json('data.*.id');

        $this->assertNotContains($task->id, $ids);
    }

    public function test_list_items_carry_tenant_assignee_and_subject_shape(): void
    {
        $task = Task::factory()->assignedTo($this->membershipOf($this->user, $this->alpha))->createdBy($this->user)->create(['title' => 'Shape']);

        $this->apiAs($this->user, $this->alpha)->getJson('/api/tasks?scope=all')
            ->assertOk()
            ->assertJsonPath('data.0.id', $task->id)
            ->assertJsonPath('data.0.tenant_name', 'Alpha')
            ->assertJsonPath('data.0.is_global', false)
            ->assertJsonPath('data.0.subject', null)
            ->assertJsonStructure(['data' => [['id', 'tenant_id', 'tenant_name', 'title', 'status', 'priority', 'due_at', 'assignee_name', 'subject', 'is_global', 'created_at']]]);
    }

    public function test_filters_and_search(): void
    {
        Task::factory()->forTenant($this->alpha)->createdBy($this->user)->create(['title' => 'Buy bleach', 'priority' => 'high']);
        Task::factory()->forTenant($this->alpha)->createdBy($this->user)->done()->create(['title' => 'Call Anna']);

        $this->apiAs($this->user, $this->alpha);

        $this->getJson('/api/tasks?filter[status]=done')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.title', 'Call Anna');
        $this->getJson('/api/tasks?filter[priority]=high')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/tasks?filter[search]=bleach')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_store_in_a_firm_other_than_the_active_one(): void
    {
        $this->apiAs($this->user, $this->alpha)
            ->postJson('/api/tasks', ['title' => 'Cross', 'priority' => 'normal', 'tenant_id' => $this->beta->id, 'source' => 'voice'])
            ->assertCreated()
            ->assertJsonPath('tenant_id', $this->beta->id)
            ->assertJsonPath('source', 'voice')
            ->assertJsonPath('created_by_name', $this->user->name);

        $this->assertSame($this->beta->id, Task::unscopedForSystem()->sole()->tenant_id);
    }

    public function test_store_in_a_foreign_firm_is_forbidden(): void
    {
        $gamma = $this->firm('Gamma');
        $this->member($gamma, 'Admin');

        $this->apiAs($this->user, $this->alpha)
            ->postJson('/api/tasks', ['title' => 'Nope', 'priority' => 'low', 'tenant_id' => $gamma->id])
            ->assertForbidden();
        $this->assertSame(0, Task::unscopedForSystem()->count());
    }

    public function test_store_with_an_assignee_in_the_active_firm(): void
    {
        $target = $this->member($this->alpha, 'Sekretárka');
        $membershipId = $this->membershipOf($target, $this->alpha)->id;

        $this->apiAs($this->user, $this->alpha)
            ->postJson('/api/tasks', [
                'title' => 'Assigned', 'priority' => 'low', 'tenant_id' => $this->alpha->id,
                'assigned_membership_id' => $membershipId,
            ])
            ->assertCreated()
            ->assertJsonPath('assigned_membership_id', $membershipId);
    }

    public function test_store_validation_errors_are_422(): void
    {
        $this->apiAs($this->user, $this->alpha)
            ->postJson('/api/tasks', ['priority' => 'urgent'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['title', 'priority']);
    }

    public function test_show_update_complete_reopen_assign_and_delete(): void
    {
        $task = Task::factory()->forTenant($this->alpha)->createdBy($this->user)->create();
        $assignee = $this->member($this->alpha, 'Vedúca');

        $this->apiAs($this->user, $this->alpha);

        $this->getJson('/api/tasks/'.$task->id)->assertOk()->assertJsonPath('can.update', true)->assertJsonPath('can.delete', true);
        $this->putJson('/api/tasks/'.$task->id, ['title' => 'Edited', 'priority' => 'high'])->assertOk()->assertJsonPath('title', 'Edited');
        $this->postJson('/api/tasks/'.$task->id.'/complete')->assertOk()->assertJsonPath('status', 'done');
        $this->postJson('/api/tasks/'.$task->id.'/complete')->assertUnprocessable();
        $this->postJson('/api/tasks/'.$task->id.'/reopen')->assertOk()->assertJsonPath('status', 'open');
        $this->postJson('/api/tasks/'.$task->id.'/assign', ['assigned_membership_id' => $this->membershipOf($assignee, $this->alpha)->id])
            ->assertOk()
            ->assertJsonPath('assignee_name', $assignee->name);
        $this->postJson('/api/tasks/'.$task->id.'/assign', ['assigned_membership_id' => null])->assertOk()->assertJsonPath('assigned_membership_id', null);
        $this->deleteJson('/api/tasks/'.$task->id)->assertNoContent();
    }

    public function test_secretary_cannot_delete_a_task_created_by_someone_else(): void
    {
        $secretary = $this->member($this->alpha, 'Sekretárka');
        $task = Task::factory()->forTenant($this->alpha)->createdBy($this->user)->assignedTo($this->membershipOf($secretary, $this->alpha))->create();

        $this->apiAs($secretary, $this->alpha);

        $this->getJson('/api/tasks/'.$task->id)->assertOk()->assertJsonPath('can.delete', false);
        $this->deleteJson('/api/tasks/'.$task->id)->assertForbidden();
        $this->assertNotSoftDeleted($task);
    }

    public function test_task_of_another_firm_is_not_found_under_the_active_firm(): void
    {
        $admin = $this->member($this->beta, 'Admin');
        $task = Task::factory()->forTenant($this->beta)->createdBy($admin)->create();

        $this->apiAs($this->user, $this->alpha)->getJson('/api/tasks/'.$task->id)->assertNotFound();
        $this->apiAs($this->user, $this->beta)->getJson('/api/tasks/'.$task->id)->assertOk();
    }

    public function test_form_context_lists_creatable_companies_and_assignable_members(): void
    {
        $gamma = $this->firm('Gamma');
        $this->member($gamma, 'Interná upratovačka', $this->user);
        $colleague = $this->member($this->alpha, 'Admin');

        $response = $this->apiAs($this->user, $this->alpha)->getJson('/api/tasks/form-context')->assertOk();

        $this->assertEqualsCanonicalizing([$this->alpha->id, $this->beta->id], $response->json('companies.*.tenant_id'));
        /** @var list<string> $names */
        $names = $response->json('members.'.$this->alpha->id.'.*.name');
        $this->assertContains($colleague->name, $names);
        $this->assertArrayNotHasKey($gamma->id, (array) $response->json('members'));
    }

    public function test_user_without_any_task_permission_is_forbidden(): void
    {
        $cleaner = $this->member($this->alpha, 'Interná upratovačka');

        $this->apiAs($cleaner, $this->alpha)->getJson('/api/tasks')->assertForbidden();
    }

    public function test_mcp_token_cannot_reach_task_routes(): void
    {
        $this->app['auth']->forgetGuards();
        Sanctum::actingAs($this->user, ['mcp']);

        $this->withHeaders(['X-Tenant-Id' => $this->alpha->id])->getJson('/api/tasks')->assertForbidden();
    }

    public function test_guest_is_unauthenticated(): void
    {
        $this->app['auth']->forgetGuards();

        $this->getJson('/api/tasks')->assertUnauthorized();
    }

    public function test_editing_a_closed_task_keeps_its_stored_assignee_even_after_that_person_lost_their_login(): void
    {
        $leaver = $this->member($this->alpha, 'Vedúca');
        $membership = $this->membershipOf($leaver, $this->alpha);
        $task = Task::factory()->assignedTo($membership)->createdBy($this->user)->done()->create();
        $membership->forceFill(['user_id' => null])->save();

        $this->apiAs($this->user, $this->alpha)
            ->putJson('/api/tasks/'.$task->id, ['title' => 'Edited', 'priority' => 'high', 'assigned_membership_id' => $membership->id])
            ->assertOk()
            ->assertJsonPath('title', 'Edited');
    }
}
