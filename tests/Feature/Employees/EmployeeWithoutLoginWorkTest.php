<?php

declare(strict_types=1);

namespace Tests\Feature\Employees;

use App\Contracts\Ai\ExtractsTaskDraft;
use App\Data\Contracts\MembershipOptionData;
use App\Data\Tasks\TaskDraftData;
use App\Enums\ContractStatusEnum;
use App\Enums\TaskPriorityEnum;
use App\Models\CleaningObject;
use App\Models\Client;
use App\Models\Contract;
use App\Models\ScheduledJob;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;
use Illuminate\Testing\TestResponse;
use Tests\Fakes\FakeTaskExtractor;
use Tests\Support\CallsMcp;
use Tests\Support\CreatesTaskFixtures;
use Tests\TestCase;

final class EmployeeWithoutLoginWorkTest extends TestCase
{
    use CallsMcp;
    use CreatesTaskFixtures;
    use RefreshDatabase;

    private Tenant $firm;

    private User $admin;

    private TenantMembership $noLogin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedPermissionCatalog();
        $this->firm = $this->firm('Alpha');
        $this->admin = $this->member($this->firm, 'Admin');
        $this->noLogin = TenantMembership::factory()->withProfile()->withoutUser()->create([
            'tenant_id' => $this->firm->id,
            'first_name' => 'Jana',
            'last_name' => 'Bezprístupu',
        ]);
    }

    /**
     * @param  TestResponse<Response>  $response
     * @return list<array<string, mixed>>
     */
    private function rows(TestResponse $response, string $path): array
    {
        $rows = $response->inertiaProps($path);

        /** @var list<array<string, mixed>> */
        return is_array($rows) ? array_values($rows) : [];
    }

    private function job(): ScheduledJob
    {
        $client = Client::factory()->create(['tenant_id' => $this->firm->id]);
        $object = CleaningObject::factory()->create(['tenant_id' => $this->firm->id, 'client_id' => $client->id]);

        return ScheduledJob::factory()->forObject($object)->create(['tenant_id' => $this->firm->id]);
    }

    public function test_a_job_can_be_assigned_to_an_employee_without_a_login(): void
    {
        $job = $this->job();

        $this->webAs($this->admin, $this->firm)->post(route('jobs.assign', $job), ['assigned_membership_id' => $this->noLogin->id])->assertRedirect();

        $this->assertSame($this->noLogin->id, $job->refresh()->assigned_membership_id);
    }

    public function test_job_and_contract_pickers_list_the_employee_without_empty_brackets(): void
    {
        $web = $this->webAs($this->admin, $this->firm);

        $jobs = $this->rows($web->get(route('jobs.create')), 'context.memberships');
        $label = collect($jobs)->firstWhere('id', $this->noLogin->id)['label'] ?? null;
        $this->assertSame('Jana Bezprístupu', $label);

        $contracts = $this->rows($web->get(route('contracts.create')), 'context.memberships');
        $this->assertSame('Jana Bezprístupu', collect($contracts)->firstWhere('id', $this->noLogin->id)['label'] ?? null);
    }

    public function test_an_employee_without_a_login_can_hold_a_contract_view_its_pdf_and_sign_it(): void
    {
        $contract = Contract::factory()->forMembership($this->noLogin)->create(['tenant_id' => $this->firm->id]);
        $web = $this->webAs($this->admin, $this->firm);

        $web->get(route('contracts.show', $contract))->assertOk();
        $web->get(route('contracts.pdf', $contract))->assertOk();
        $web->post(route('contracts.sign', $contract))->assertSessionHasNoErrors();

        $this->assertSame(ContractStatusEnum::Active, $contract->refresh()->status);
    }

    public function test_the_option_label_shows_the_contact_email_when_there_is_one(): void
    {
        $this->noLogin->update(['contact_email' => 'kontakt@example.com']);

        $this->assertSame('Jana Bezprístupu (kontakt@example.com)', MembershipOptionData::fromModel($this->noLogin->refresh())->label);
    }

    public function test_a_task_cannot_be_assigned_to_an_employee_without_a_login_on_create_or_assign(): void
    {
        $web = $this->webAs($this->admin, $this->firm);
        $task = Task::factory()->forTenant($this->firm)->createdBy($this->admin)->create();

        $web->post(route('tasks.store'), [
            'title' => 'Úloha', 'priority' => 'low', 'tenant_id' => $this->firm->id,
            'assigned_membership_id' => $this->noLogin->id,
        ])->assertSessionHasErrors('assigned_membership_id');
        $web->post(route('tasks.assign', $task), ['assigned_membership_id' => $this->noLogin->id])->assertSessionHasErrors('assigned_membership_id');

        $this->assertNull($task->refresh()->assigned_membership_id);
    }

    public function test_the_mobile_api_and_mcp_refuse_the_same_assignee(): void
    {
        $task = Task::factory()->forTenant($this->firm)->createdBy($this->admin)->create();

        $this->apiAs($this->admin, $this->firm)->postJson('/api/tasks/'.$task->id.'/assign', ['assigned_membership_id' => $this->noLogin->id])->assertUnprocessable();

        $token = $this->mcpToken($this->admin);
        $result = $this->callTool($token, 'assign_task', [
            'company_id' => $this->firm->id,
            'task_id' => $task->id,
            'assigned_membership_id' => $this->noLogin->id,
        ]);

        $this->assertTrue($result->isError);
        $this->assertNull($task->refresh()->assigned_membership_id);
    }

    public function test_task_form_options_and_voice_parsing_list_only_people_with_a_login(): void
    {
        $colleague = $this->member($this->firm, 'Sekretárka');
        $colleagueMembership = $this->membershipOf($colleague, $this->firm);

        $options = $this->apiAs($this->admin, $this->firm)->getJson('/api/tasks/form-context')->assertOk()->json('members.'.$this->firm->id.'.*.id');
        $this->assertContains($colleagueMembership->id, (array) $options);
        $this->assertNotContains($this->noLogin->id, (array) $options);

        $extractor = new FakeTaskExtractor;
        $this->app->instance(ExtractsTaskDraft::class, $extractor);
        $extractor->draft = new TaskDraftData('T', null, null, TaskPriorityEnum::Low, $this->firm->id, $this->noLogin->id, 'Jana');

        $this->apiAs($this->admin, $this->firm)->postJson('/api/tasks/parse', ['text' => 'x', 'company_id' => $this->firm->id])
            ->assertOk()
            ->assertJsonPath('assigned_membership_id', null);
        $this->assertNotContains($this->noLogin->id, array_column($extractor->calls[0][1]->members, 'id'));
    }
}
