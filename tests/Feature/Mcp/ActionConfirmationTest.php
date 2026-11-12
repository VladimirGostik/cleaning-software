<?php

declare(strict_types=1);

namespace Tests\Feature\Mcp;

use App\Enums\ActionChannelEnum;
use App\Enums\ConfirmationChannelEnum;
use App\Enums\ContractStatusEnum;
use App\Enums\DocumentTypeEnum;
use App\Enums\InvoiceStatusEnum;
use App\Enums\PendingActionEnum;
use App\Enums\PendingActionStatusEnum;
use App\Enums\QuoteStatusEnum;
use App\Models\CleaningObject;
use App\Models\Client;
use App\Models\Contract;
use App\Models\Document;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoiceNumberSequence;
use App\Models\PendingAction;
use App\Models\Quote;
use App\Models\QuoteItem;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Ai\ActionConfirmationService;
use App\Services\Ai\ToolContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia;
use RuntimeException;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\BuildsTaskAttachmentFiles;
use Tests\Support\CreatesMcpFixtures;
use Tests\TestCase;

final class ActionConfirmationTest extends TestCase
{
    use BuildsTaskAttachmentFiles;
    use CreatesMcpFixtures;
    use RefreshDatabase;

    private Tenant $alpha;

    private Tenant $beta;

    private User $admin;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedPermissionCatalog();
        $this->alpha = $this->firm('Alpha');
        $this->beta = $this->firm('Beta');
        $this->admin = $this->member($this->alpha, 'Admin');
        $this->member($this->beta, 'Admin', $this->admin);
        $this->token = $this->mcpToken($this->admin);
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array{pending: PendingAction, token: string}
     */
    private function prepare(PendingActionEnum $action, string $subjectId, ?Tenant $tenant = null, ?string $token = null, array $extra = []): array
    {
        $result = $this->callTool($token ?? $this->token, 'prepare_action', [
            'company_id' => ($tenant ?? $this->alpha)->id,
            'action' => $action->value,
            'subject_id' => $subjectId,
            ...$extra,
        ]);

        $this->assertFalse($result->isError, $result->text);

        return [
            'pending' => PendingAction::query()->findOrFail($result->string('pending_action.id')),
            'token' => $result->string('token'),
        ];
    }

    private function draftInvoice(?Tenant $tenant = null): Invoice
    {
        return Invoice::factory()->create(['tenant_id' => ($tenant ?? $this->alpha)->id]);
    }

    public function test_prepare_then_confirm_issues_exactly_one_number_and_replay_fails(): void
    {
        $invoice = $this->draftInvoice();

        ['pending' => $pending, 'token' => $token] = $this->prepare(PendingActionEnum::IssueInvoice, $invoice->id);

        $this->assertSame(PendingActionStatusEnum::Pending, $pending->status);
        $this->assertSame(ActionChannelEnum::Mcp, $pending->channel);
        $this->assertNotSame($token, $pending->token_hash);
        $this->assertSame(hash('sha256', $token), $pending->token_hash);
        $this->assertSame(InvoiceStatusEnum::Draft, $invoice->refresh()->status);

        $confirmed = $this->callTool($this->token, 'confirm_action', ['token' => $token]);
        $this->assertFalse($confirmed->isError, $confirmed->text);

        $invoice->refresh();
        $this->assertSame(InvoiceStatusEnum::Issued, $invoice->status);
        $this->assertNotNull($invoice->number);
        $this->assertSame(1, InvoiceNumberSequence::withoutGlobalScopes()->where('tenant_id', $this->alpha->id)->value('last_number'));
        $this->assertSame(PendingActionStatusEnum::Executed, $pending->refresh()->status);
        $this->assertSame(ConfirmationChannelEnum::Chat, $pending->refresh()->confirmed_via);

        $replay = $this->callTool($this->token, 'confirm_action', ['token' => $token]);
        $this->assertTrue($replay->isError);
        $this->assertSame(1, InvoiceNumberSequence::withoutGlobalScopes()->where('tenant_id', $this->alpha->id)->value('last_number'));

        $this->assertSame(
            ['tool.prepare_action', 'tool.confirm', 'tool.confirm.failed'],
            $this->aiActivities()->pluck('description')->all(),
        );
        $this->assertSame([$this->alpha->id, $this->alpha->id, null], $this->aiActivities()->pluck('tenant_id')->all());
        $this->assertStringNotContainsString($token, (string) json_encode($this->aiActivities()->pluck('properties')->all()));
    }

    public function test_a_token_of_user_a_used_by_user_b_fails(): void
    {
        $invoice = $this->draftInvoice();
        ['token' => $token] = $this->prepare(PendingActionEnum::IssueInvoice, $invoice->id);

        $other = $this->member($this->alpha, 'Admin');
        $result = $this->callTool($this->mcpToken($other), 'confirm_action', ['token' => $token]);

        $this->assertTrue($result->isError);
        $this->assertSame(InvoiceStatusEnum::Draft, $invoice->refresh()->status);
        $this->assertSame(PendingActionStatusEnum::Pending, PendingAction::query()->sole()->status);
    }

    public function test_cancel_invoice_is_refused_in_chat_and_confirmed_in_the_app(): void
    {
        $invoice = Invoice::factory()->issued()->create(['tenant_id' => $this->alpha->id]);
        ['pending' => $pending, 'token' => $token] = $this->prepare(PendingActionEnum::CancelInvoice, $invoice->id);

        $this->assertTrue($this->callTool($this->token, 'confirm_action', ['token' => $token])->isError);
        $this->assertSame(InvoiceStatusEnum::Issued, $invoice->refresh()->status);

        $this->webAs($this->admin, $this->alpha)->post(route('pending-actions.confirm', $pending))->assertRedirect();

        $this->assertSame(InvoiceStatusEnum::Cancelled, $invoice->refresh()->status);
        $this->assertSame(ConfirmationChannelEnum::App, $pending->refresh()->confirmed_via);
        $this->assertContains('tool.confirm', $this->aiActivities()->pluck('description')->all());
        $this->assertContains('tool.confirm.failed', $this->aiActivities()->pluck('description')->all());
    }

    public function test_prepare_authorises_with_the_policy_and_stores_nothing_when_denied(): void
    {
        $invoice = $this->draftInvoice();
        $reader = $this->member($this->alpha, 'Účtovníčka');
        $this->grant($reader, $this->alpha, 'view invoices');
        $reader->revokePermissionTo('edit invoices');
        $reader->roles()->detach();
        $reader->unsetRelation('roles')->unsetRelation('permissions');

        $result = $this->callTool($this->mcpToken($reader), 'prepare_action', [
            'company_id' => $this->alpha->id,
            'action' => 'issue_invoice',
            'subject_id' => $invoice->id,
        ]);

        $this->assertTrue($result->isError);
        $this->assertSame(0, PendingAction::query()->count());
    }

    public function test_losing_the_permission_between_prepare_and_confirm_refuses_and_keeps_the_row_pending(): void
    {
        $accountant = $this->member($this->alpha, 'Účtovníčka');
        $accountantToken = $this->mcpToken($accountant);
        $invoice = $this->draftInvoice();
        ['pending' => $pending, 'token' => $token] = $this->prepare(PendingActionEnum::IssueInvoice, $invoice->id, token: $accountantToken);

        $accountant->roles()->detach();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $result = $this->callTool($accountantToken, 'confirm_action', ['token' => $token]);

        $this->assertTrue($result->isError);
        $this->assertSame(PendingActionStatusEnum::Pending, $pending->refresh()->status);
        $this->assertSame(InvoiceStatusEnum::Draft, $invoice->refresh()->status);
    }

    public function test_a_failing_service_call_rolls_back_the_row_and_the_number_and_audits_the_failure_once(): void
    {
        $this->alpha->update(['ico' => null]);
        $invoice = $this->draftInvoice();
        ['pending' => $pending, 'token' => $token] = $this->prepare(PendingActionEnum::IssueInvoice, $invoice->id);

        $result = $this->callTool($this->token, 'confirm_action', ['token' => $token]);

        $this->assertTrue($result->isError);
        $this->assertSame(PendingActionStatusEnum::Pending, $pending->refresh()->status);
        $this->assertNull($pending->refresh()->executed_at);
        $this->assertNull($invoice->refresh()->number);
        $this->assertSame(0, InvoiceNumberSequence::withoutGlobalScopes()->where('tenant_id', $this->alpha->id)->count());

        $descriptions = $this->aiActivities()->pluck('description')->all();
        $this->assertSame(['tool.prepare_action', 'tool.confirm.failed'], $descriptions);
        $this->assertNotContains('tool.confirm', $descriptions);
    }

    public function test_a_second_confirm_of_one_row_cannot_execute_it_again_and_execution_takes_the_row_lock(): void
    {
        $invoice = $this->draftInvoice();
        ['pending' => $pending] = $this->prepare(PendingActionEnum::IssueInvoice, $invoice->id);
        $service = app(ActionConfirmationService::class);

        $locks = [];
        DB::listen(function (QueryExecuted $query) use (&$locks): void {
            if (str_contains($query->sql, 'pending_actions') && str_contains(strtolower($query->sql), 'for update')) {
                $locks[] = $query->sql;
            }
        });

        $service->execute($this->admin, $pending, ConfirmationChannelEnum::Chat);

        $this->assertNotEmpty($locks);
        $this->expectException(ValidationException::class);

        try {
            $service->execute($this->admin, PendingAction::findOrFail($pending->id), ConfirmationChannelEnum::Chat);
        } finally {
            $this->assertSame(1, InvoiceNumberSequence::withoutGlobalScopes()->where('tenant_id', $this->alpha->id)->value('last_number'));
        }
    }

    public function test_a_stale_in_memory_copy_cannot_execute_twice(): void
    {
        $invoice = $this->draftInvoice();
        ['pending' => $pending] = $this->prepare(PendingActionEnum::IssueInvoice, $invoice->id);
        $stale = PendingAction::findOrFail($pending->id);
        $service = app(ActionConfirmationService::class);

        $service->execute($this->admin, $pending, ConfirmationChannelEnum::Chat);

        $this->assertSame(PendingActionStatusEnum::Pending, $stale->status);
        $this->expectException(ValidationException::class);
        $service->execute($this->admin, $stale, ConfirmationChannelEnum::Chat);
    }

    public function test_web_confirm_of_another_users_action_is_forbidden(): void
    {
        $invoice = $this->draftInvoice();
        ['pending' => $pending] = $this->prepare(PendingActionEnum::IssueInvoice, $invoice->id);
        $other = $this->member($this->alpha, 'Admin');

        $this->webAs($other, $this->alpha)->post(route('pending-actions.confirm', $pending))->assertForbidden();
        $this->webAs($other, $this->alpha)->post(route('pending-actions.cancel', $pending))->assertForbidden();

        $this->assertSame(PendingActionStatusEnum::Pending, $pending->refresh()->status);
    }

    public function test_web_confirm_ignores_a_supplied_token_and_unknown_ids_are_not_found(): void
    {
        $invoice = $this->draftInvoice();
        ['pending' => $pending, 'token' => $token] = $this->prepare(PendingActionEnum::CancelInvoice, Invoice::factory()->issued()->create(['tenant_id' => $this->alpha->id])->id);

        $this->webAs($this->admin, $this->alpha)->post(route('pending-actions.confirm', ['pendingAction' => (string) Str::uuid()]), ['token' => $token])->assertNotFound();
        $this->assertSame(PendingActionStatusEnum::Pending, $pending->refresh()->status);
        $this->assertSame(InvoiceStatusEnum::Draft, $invoice->refresh()->status);
    }

    public function test_web_confirm_of_a_row_in_another_firm_executes_there_and_keeps_the_active_tenant(): void
    {
        $invoice = $this->draftInvoice($this->beta);
        ['pending' => $pending] = $this->prepare(PendingActionEnum::IssueInvoice, $invoice->id, $this->beta);

        $this->webAs($this->admin, $this->alpha)
            ->post(route('pending-actions.confirm', $pending))
            ->assertRedirect()
            ->assertSessionHas('active_tenant_id', $this->alpha->id);

        $this->assertSame(InvoiceStatusEnum::Issued, $invoice->refresh()->status);
        $this->assertSame(1, InvoiceNumberSequence::withoutGlobalScopes()->where('tenant_id', $this->beta->id)->value('last_number'));
        $this->assertSame(0, InvoiceNumberSequence::withoutGlobalScopes()->where('tenant_id', $this->alpha->id)->count());
        $this->assertSame($this->alpha->id, app('current_tenant_id'));
        $this->assertSame($this->alpha->id, app(PermissionRegistrar::class)->getPermissionsTeamId());
    }

    public function test_revoking_the_permission_in_the_target_firm_only_refuses_the_web_confirm(): void
    {
        $user = $this->member($this->alpha, 'Admin');
        $this->member($this->beta, 'Účtovníčka', $user);
        $invoice = Invoice::factory()->issued()->create(['tenant_id' => $this->beta->id]);
        $this->grant($user, $this->beta, 'cancel invoices');
        ['pending' => $pending] = $this->prepare(PendingActionEnum::CancelInvoice, $invoice->id, $this->beta, $this->mcpToken($user));

        $user->revokePermissionTo('cancel invoices');
        $user->roles()->detach();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->webAs($user, $this->alpha)
            ->post(route('pending-actions.confirm', $pending))
            ->assertForbidden();

        $this->assertSame(PendingActionStatusEnum::Pending, $pending->refresh()->status);
        $this->assertSame(InvoiceStatusEnum::Issued, $invoice->refresh()->status);
        $this->assertSame($this->alpha->id, app('current_tenant_id'));
    }

    public function test_an_exception_inside_the_transaction_restores_tenant_and_team_and_leaves_one_failure_row(): void
    {
        $this->alpha->update(['ico' => null]);
        $invoice = $this->draftInvoice();
        ['pending' => $pending] = $this->prepare(PendingActionEnum::IssueInvoice, $invoice->id);
        $this->aiActivities()->each->delete();

        $registrar = app(PermissionRegistrar::class);
        $this->bindTenant($this->beta);
        $this->actingAs($this->admin);

        try {
            app(ActionConfirmationService::class)->execute($this->admin, $pending, ConfirmationChannelEnum::Chat);
            $this->fail('Expected the incomplete supplier profile to fail the issue.');
        } catch (ValidationException) {
            $this->assertSame($this->beta->id, app('current_tenant_id'));
            $this->assertSame($this->beta->id, $registrar->getPermissionsTeamId());
        }

        $rows = $this->aiActivities();
        $this->assertSame(['tool.confirm.failed'], $rows->pluck('description')->all());
        $this->assertSame($this->alpha->id, $rows->sole()->tenant_id);
        $this->assertSame(PendingActionStatusEnum::Pending, $pending->refresh()->status);
    }

    public function test_an_assistant_row_refuses_chat_confirmation_even_when_config_allows_it(): void
    {
        config()->set('ai.confirm_channels.issue_invoice', ['chat', 'app']);
        $invoice = $this->draftInvoice();
        $prepared = app(ActionConfirmationService::class)->prepare($this->admin, $this->alpha->id, PendingActionEnum::IssueInvoice, $invoice, [], ActionChannelEnum::Assistant);

        $this->assertNull($prepared->token);
        $this->assertTrue($prepared->pending_action->confirm_in_app_only);

        $row = PendingAction::findOrFail($prepared->pending_action->id);
        $this->assertNull($row->token_hash);

        try {
            app(ActionConfirmationService::class)->execute($this->admin, $row, ConfirmationChannelEnum::Chat);
            $this->fail('Chat confirmation of an assistant row must be refused.');
        } catch (ValidationException) {
            $this->assertSame(InvoiceStatusEnum::Draft, $invoice->refresh()->status);
        }

        app(ActionConfirmationService::class)->execute($this->admin, $row, ConfirmationChannelEnum::App);
        $this->assertSame(InvoiceStatusEnum::Issued, $invoice->refresh()->status);
    }

    public function test_expired_rows_refuse_confirmation_and_the_cleanup_command_flips_them(): void
    {
        $invoice = $this->draftInvoice();
        ['pending' => $pending, 'token' => $token] = $this->prepare(PendingActionEnum::IssueInvoice, $invoice->id);
        $pending->update(['expires_at' => now()->subMinute()]);

        $this->assertTrue($this->callTool($this->token, 'confirm_action', ['token' => $token])->isError);
        $this->webAs($this->admin, $this->alpha)->post(route('pending-actions.confirm', $pending))->assertSessionHasErrors('status');
        $this->assertSame(InvoiceStatusEnum::Draft, $invoice->refresh()->status);

        $this->artisanCommand('cleanup:pending-actions')->assertSuccessful();
        $this->assertSame(PendingActionStatusEnum::Expired, $pending->refresh()->status);
    }

    public function test_delete_task_is_a_two_step_app_only_action_for_firm_tasks_and_direct_for_own_global_tasks(): void
    {
        $firm = Task::factory()->forTenant($this->alpha)->createdBy($this->admin)->create();
        ['pending' => $pending, 'token' => $token] = $this->prepare(PendingActionEnum::DeleteTask, $firm->id);

        $this->assertTrue($this->callTool($this->token, 'confirm_action', ['token' => $token])->isError);
        $this->assertNotNull(Task::unscopedForSystem()->find($firm->id));

        $this->webAs($this->admin, $this->alpha)->post(route('pending-actions.confirm', $pending))->assertRedirect();
        $this->assertNull(Task::unscopedForSystem()->find($firm->id));
        $this->assertNotNull(Task::unscopedForSystem()->withTrashed()->find($firm->id)?->deleted_at);

        $global = Task::factory()->global()->createdBy($this->admin)->create();
        $refused = $this->callTool($this->token, 'prepare_action', ['company_id' => $this->alpha->id, 'action' => 'delete_task', 'subject_id' => $global->id]);
        $this->assertTrue($refused->isError);
        $this->assertNotNull(Task::unscopedForSystem()->find($global->id));
        $this->assertSame(1, PendingAction::query()->count());
    }

    public function test_viewing_a_task_and_adding_an_attachment_does_not_cancel_a_pending_delete_task(): void
    {
        Storage::fake('local');
        $firm = Task::factory()->forTenant($this->alpha)->createdBy($this->admin)->create();
        ['pending' => $pending] = $this->prepare(PendingActionEnum::DeleteTask, $firm->id);

        $this->webAs($this->admin, $this->alpha)->get(route('tasks.index', ['task' => $firm->id]))->assertOk();
        $this->webAs($this->admin, $this->alpha)->post(route('tasks.attachments.store', $firm), [
            'file' => $this->attachmentFile('photo.jpg'),
        ])->assertRedirect();
        $this->webAs($this->admin, $this->alpha)->post(route('pending-actions.confirm', $pending))->assertRedirect();

        $this->assertSame(PendingActionStatusEnum::Executed, $pending->refresh()->status);
        $this->assertNull(Task::unscopedForSystem()->find($firm->id));
    }

    public function test_delete_global_task_is_direct_creator_only_and_audited_as_executed(): void
    {
        $global = Task::factory()->global()->createdBy($this->admin)->create();
        $foreignGlobal = Task::factory()->global()->createdBy(User::factory()->create())->create();
        $firm = Task::factory()->forTenant($this->alpha)->createdBy($this->admin)->create();

        $this->assertTrue($this->callTool($this->token, 'delete_global_task', ['task_id' => $foreignGlobal->id])->isError);
        $this->assertTrue($this->callTool($this->token, 'delete_global_task', ['task_id' => $firm->id])->isError);
        $this->assertNotNull(Task::unscopedForSystem()->find($foreignGlobal->id));
        $this->assertNotNull(Task::unscopedForSystem()->find($firm->id));

        $done = $this->callTool($this->token, 'delete_global_task', ['task_id' => $global->id]);

        $this->assertFalse($done->isError, $done->text);
        $this->assertNull(Task::unscopedForSystem()->find($global->id));
        $row = $this->aiActivities()->firstWhere('description', 'tool.delete_global_task');
        $this->assertNotNull($row);
        $this->assertNull($row->tenant_id);
        $this->assertTrue(Arr::get($row->propertiesArray() ?? [], 'executed'));
        $this->assertSame(0, PendingAction::query()->count());
    }

    public function test_every_other_action_maps_to_its_service_and_follows_the_channel_rules(): void
    {
        $object = CleaningObject::factory()->create(['tenant_id' => $this->alpha->id, 'client_id' => Client::factory()->create(['tenant_id' => $this->alpha->id])->id]);
        $contract = Contract::factory()->draft()->forObject($object)->create(['tenant_id' => $this->alpha->id]);
        $quote = Quote::factory()->create(['tenant_id' => $this->alpha->id]);
        $sendInvoice = Invoice::factory()->issued()->create(['tenant_id' => $this->alpha->id, 'customer_email' => 'buyer@example.com']);
        $draftInvoice = $this->draftInvoice();
        $deleteQuote = Quote::factory()->create(['tenant_id' => $this->alpha->id]);
        $expense = Expense::factory()->forTenant($this->alpha)->createdBy($this->admin)->create();

        $sign = $this->prepare(PendingActionEnum::SignContract, $contract->id);
        $this->assertTrue($sign['pending']->allowedConfirmationChannels() === [ConfirmationChannelEnum::App]);
        $this->assertTrue($this->callTool($this->token, 'confirm_action', ['token' => $sign['token']])->isError);
        $this->webAs($this->admin, $this->alpha)->post(route('pending-actions.confirm', $sign['pending']))->assertRedirect();
        $this->assertSame(ContractStatusEnum::Active, $contract->refresh()->status);

        $send = $this->prepare(PendingActionEnum::SendQuote, $quote->id);
        $this->assertFalse($this->callTool($this->token, 'confirm_action', ['token' => $send['token']])->isError);
        $this->assertSame(QuoteStatusEnum::Sent, $quote->refresh()->status);

        $mail = $this->prepare(PendingActionEnum::SendInvoice, $sendInvoice->id);
        $this->assertFalse($this->callTool($this->token, 'confirm_action', ['token' => $mail['token']])->isError);

        foreach ([[PendingActionEnum::DeleteInvoiceDraft, $draftInvoice], [PendingActionEnum::DeleteQuote, $deleteQuote], [PendingActionEnum::DeleteExpense, $expense]] as [$action, $subject]) {
            $row = $this->prepare($action, $subject->id);
            $this->assertTrue($this->callTool($this->token, 'confirm_action', ['token' => $row['token']])->isError, $action->value);
            $this->assertNotNull($subject::query()->find($subject->id), $action->value);

            $this->webAs($this->admin, $this->alpha)->post(route('pending-actions.confirm', $row['pending']))->assertRedirect();
            $this->assertNull($subject::query()->find($subject->id), $action->value);
        }
    }

    public function test_previews_describe_the_subject_and_never_contain_the_token(): void
    {
        $invoice = Invoice::factory()->create(['tenant_id' => $this->alpha->id, 'customer_name' => 'Acme s.r.o.', 'total' => '123.40']);

        ['pending' => $pending, 'token' => $token] = $this->prepare(PendingActionEnum::IssueInvoice, $invoice->id, extra: ['number' => '2026-0042']);

        $this->assertStringContainsString('Acme s.r.o.', $pending->preview);
        $this->assertStringContainsString('123.40', $pending->preview);
        $this->assertStringContainsString('2026-0042', $pending->preview);
        $this->assertStringNotContainsString($token, $pending->preview);
        $this->assertSame(['number' => '2026-0042'], $pending->payload);

        $this->callTool($this->token, 'confirm_action', ['token' => $token]);
        $this->assertSame('2026-0042', $invoice->refresh()->number);
    }

    public function test_prepare_needs_a_subject_of_the_given_company_and_the_matching_kind(): void
    {
        $foreign = $this->draftInvoice($this->beta);

        $this->assertTrue($this->callTool($this->token, 'prepare_action', ['company_id' => $this->alpha->id, 'action' => 'issue_invoice', 'subject_id' => $foreign->id])->isError);
        $this->assertTrue($this->callTool($this->token, 'prepare_action', ['company_id' => $this->alpha->id, 'action' => 'delete_quote', 'subject_id' => $this->draftInvoice()->id])->isError);
        $this->assertTrue($this->callTool($this->token, 'prepare_action', ['company_id' => $this->alpha->id, 'action' => 'launch_rocket', 'subject_id' => $foreign->id])->isError);
        $this->assertSame(0, PendingAction::query()->count());
    }

    public function test_cancel_works_for_the_owner_on_web_and_api_and_blocks_confirmation(): void
    {
        $invoice = $this->draftInvoice();
        ['pending' => $pending, 'token' => $token] = $this->prepare(PendingActionEnum::IssueInvoice, $invoice->id);

        $this->webAs($this->admin, $this->alpha)->post(route('pending-actions.cancel', $pending))->assertRedirect();
        $this->assertSame(PendingActionStatusEnum::Cancelled, $pending->refresh()->status);

        $this->assertTrue($this->callTool($this->token, 'confirm_action', ['token' => $token])->isError);
        $this->apiAs($this->admin, $this->alpha)->postJson(route('api.pending-actions.cancel', $pending))->assertUnprocessable();

        ['pending' => $second] = $this->prepare(PendingActionEnum::IssueInvoice, $invoice->id);
        $this->apiAs($this->admin, $this->alpha)->postJson(route('api.pending-actions.cancel', $second))->assertOk()->assertJsonPath('status', 'cancelled');
    }

    public function test_the_web_index_and_the_api_list_expose_no_token_material(): void
    {
        ['pending' => $pending, 'token' => $token] = $this->prepare(PendingActionEnum::IssueInvoice, $this->draftInvoice()->id);

        $this->webAs($this->admin, $this->alpha)->get(route('pending-actions.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('PendingActions/Index', false)->has('pendingActions', 1)->where('pendingActions.0.id', $pending->id));

        $response = $this->apiAs($this->admin, $this->alpha)->getJson(route('api.pending-actions.index'))->assertOk();
        $body = (string) $response->getContent();

        $this->assertStringNotContainsString($token, $body);
        $this->assertStringNotContainsString('token_hash', $body);
        $this->assertStringNotContainsString('"token"', $body);
        $response->assertJsonPath('0.id', $pending->id)->assertJsonPath('0.confirm_in_app_only', false)->assertJsonPath('0.tenant_name', 'Alpha');
    }

    public function test_the_api_confirm_runs_under_the_rows_firm_and_refuses_firms_without_mobile_access(): void
    {
        $user = $this->member($this->alpha, 'Admin');
        $this->member($this->beta, 'Účtovníčka', $user);
        $userToken = $this->mcpToken($user);
        $inAlpha = $this->draftInvoice();
        $inBeta = $this->draftInvoice($this->beta);
        ['pending' => $alphaRow] = $this->prepare(PendingActionEnum::IssueInvoice, $inAlpha->id, $this->alpha, $userToken);
        ['pending' => $betaRow] = $this->prepare(PendingActionEnum::IssueInvoice, $inBeta->id, $this->beta, $userToken);

        $this->apiAs($user, $this->alpha);
        $this->grant($user, $this->beta, 'access mobile app');
        $user->revokePermissionTo('access mobile app');
        $user->unsetRelation('roles')->unsetRelation('permissions');

        $this->withHeaders(['X-Tenant-Id' => $this->alpha->id])->getJson(route('api.pending-actions.index'))
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.id', $alphaRow->id);

        $this->withHeaders(['X-Tenant-Id' => $this->alpha->id])->postJson(route('api.pending-actions.confirm', $betaRow))->assertForbidden()->assertJsonPath('message', __('app.mobile_forbidden'));
        $this->assertSame(PendingActionStatusEnum::Pending, $betaRow->refresh()->status);
        $this->assertSame(InvoiceStatusEnum::Draft, $inBeta->refresh()->status);

        $this->withHeaders(['X-Tenant-Id' => $this->alpha->id])->postJson(route('api.pending-actions.confirm', $alphaRow))
            ->assertOk()
            ->assertJsonPath('action', 'issue_invoice');
        $this->assertSame(InvoiceStatusEnum::Issued, $inAlpha->refresh()->status);
    }

    public function test_tool_context_restores_tenant_team_user_and_permission_cache_when_nested(): void
    {
        $user = $this->member($this->alpha, 'Admin');
        $this->member($this->beta, 'Interná upratovačka', $user);
        $context = app(ToolContext::class);
        $registrar = app(PermissionRegistrar::class);

        $this->bindTenant($this->alpha);
        $this->actingAs($user);
        $user->unsetRelation('roles')->unsetRelation('permissions');
        $this->assertTrue($user->can('create invoices'));

        $seen = fn (): array => $this->contextSnapshot($user);

        /** @var array{0: list<mixed>, 1: list<mixed>, 2: list<mixed>} $inner */
        $inner = $context->run($user, $this->beta->id, fn (): array => [
            $seen(),
            $context->run($user, $this->alpha->id, $seen),
            $seen(),
        ]);

        $this->assertSame([$this->beta->id, $this->beta->id, false], $inner[0]);
        $this->assertSame([$this->alpha->id, $this->alpha->id, true], $inner[1]);
        $this->assertSame([$this->beta->id, $this->beta->id, false], $inner[2]);
        $this->assertSame($this->alpha->id, app('current_tenant_id'));
        $this->assertSame($this->alpha->id, $registrar->getPermissionsTeamId());
        $this->assertTrue($user->can('create invoices'));

        try {
            $context->run($user, $this->beta->id, fn (): never => throw new RuntimeException('boom'));
        } catch (RuntimeException) {
            $this->assertSame($this->alpha->id, app('current_tenant_id'));
            $this->assertSame($this->alpha->id, $registrar->getPermissionsTeamId());
            $this->assertTrue($user->can('create invoices'));
        }

        $this->expectException(AuthorizationException::class);
        $context->run($user, $this->firm('Elsewhere')->id, fn (): null => null);
    }

    /** @return list<mixed> */
    private function contextSnapshot(User $user): array
    {
        return [app('current_tenant_id'), app(PermissionRegistrar::class)->getPermissionsTeamId(), $user->can('create invoices')];
    }

    public function test_a_subject_changed_after_prepare_is_refused_and_the_row_stays_pending(): void
    {
        $invoice = $this->draftInvoice();
        InvoiceItem::factory()->create(['tenant_id' => $this->alpha->id, 'invoice_id' => $invoice->id, 'unit_price' => '100.00', 'quantity' => '1.00']);
        ['pending' => $pending, 'token' => $token] = $this->prepare(PendingActionEnum::IssueInvoice, $invoice->id);

        $changed = $this->callTool($this->token, 'update_invoice_draft', [
            'company_id' => $this->alpha->id,
            'invoice_id' => $invoice->id,
            'customer_name' => 'Attacker',
            'items' => [['description' => 'Gold', 'quantity' => 9999, 'unit_price' => 1000]],
        ]);
        $this->assertFalse($changed->isError, $changed->text);

        $result = $this->callTool($this->token, 'confirm_action', ['token' => $token]);

        $this->assertTrue($result->isError);
        $this->assertStringContainsString(__('app.pending_action_subject_changed'), $result->text);
        $this->assertSame(InvoiceStatusEnum::Draft, $invoice->refresh()->status);
        $this->assertNull($invoice->number);
        $this->assertSame(PendingActionStatusEnum::Cancelled, $pending->refresh()->status);
        $this->assertContains('tool.confirm.failed', $this->aiActivities()->pluck('description')->all());
    }

    public function test_an_items_only_change_is_refused_too(): void
    {
        $invoice = $this->draftInvoice();
        InvoiceItem::factory()->create(['tenant_id' => $this->alpha->id, 'invoice_id' => $invoice->id]);
        ['token' => $token] = $this->prepare(PendingActionEnum::IssueInvoice, $invoice->id);

        InvoiceItem::query()->where('invoice_id', $invoice->id)->update(['unit_price' => '999999.00']);

        $this->assertTrue($this->callTool($this->token, 'confirm_action', ['token' => $token])->isError);
        $this->assertSame(InvoiceStatusEnum::Draft, $invoice->refresh()->status);
    }

    public function test_an_edited_contract_text_after_prepare_is_refused(): void
    {
        $contract = $this->draftContract();
        $document = $this->textDocument($contract, 'Cena 100 EUR');
        $pending = $this->prepare(PendingActionEnum::SignContract, $contract->id)['pending'];

        $document->update(['body' => 'Cena 9999 EUR']);

        $this->webAs($this->admin, $this->alpha)->post(route('pending-actions.confirm', $pending))
            ->assertSessionHasErrors(['status' => __('app.pending_action_subject_changed')]);
        $this->assertSame(ContractStatusEnum::Draft, $contract->refresh()->status);
    }

    public function test_an_edited_quote_text_after_prepare_is_refused(): void
    {
        $quote = Quote::factory()->create(['tenant_id' => $this->alpha->id]);
        $document = $this->textDocument($quote, 'Pôvodný text');
        $token = $this->prepare(PendingActionEnum::SendQuote, $quote->id)['token'];

        $document->update(['body' => 'Podvrhnutý text']);

        $result = $this->callTool($this->token, 'confirm_action', ['token' => $token]);

        $this->assertTrue($result->isError);
        $this->assertStringContainsString(__('app.pending_action_subject_changed'), $result->text);
        $this->assertSame(QuoteStatusEnum::Draft, $quote->refresh()->status);
    }

    public function test_an_unchanged_text_resaved_with_a_new_ciphertext_still_confirms(): void
    {
        $quote = Quote::factory()->create(['tenant_id' => $this->alpha->id]);
        $document = $this->textDocument($quote, 'Rovnaký text');
        $token = $this->prepare(PendingActionEnum::SendQuote, $quote->id)['token'];

        DB::table('documents')->where('id', $document->id)->update(['body' => encrypt('Rovnaký text', false)]);

        $this->assertFalse($this->callTool($this->token, 'confirm_action', ['token' => $token])->isError);
        $this->assertSame(QuoteStatusEnum::Sent, $quote->refresh()->status);
    }

    public function test_signing_a_contract_with_unresolved_tokens_fails_cleanly_and_stays_a_draft(): void
    {
        $contract = $this->draftContract();
        $this->textDocument($contract, '<p>Cena {{contract.price}}</p>');
        $pending = $this->prepare(PendingActionEnum::SignContract, $contract->id)['pending'];

        $this->webAs($this->admin, $this->alpha)->post(route('pending-actions.confirm', $pending))
            ->assertSessionHasErrors('status');

        $this->assertSame(ContractStatusEnum::Draft, $contract->refresh()->status);
    }

    private function draftContract(): Contract
    {
        $object = CleaningObject::factory()->create(['tenant_id' => $this->alpha->id, 'client_id' => Client::factory()->create(['tenant_id' => $this->alpha->id])->id]);

        return Contract::factory()->draft()->forObject($object)->create(['tenant_id' => $this->alpha->id]);
    }

    private function textDocument(Contract|Quote $subject, string $body): Document
    {
        $existing = $subject->document()->first();

        if ($existing instanceof Document) {
            $existing->update(['body' => $body]);

            return $existing;
        }

        return Document::factory()->create([
            'tenant_id' => $subject->tenant_id,
            'documentable_type' => $subject->getMorphClass(),
            'documentable_id' => $subject->id,
            'type' => $subject instanceof Contract ? DocumentTypeEnum::Other : DocumentTypeEnum::Quote,
            'body' => $body,
        ]);
    }

    public function test_send_quote_refuses_a_swapped_recipient_and_delete_expense_a_changed_amount(): void
    {
        $quote = Quote::factory()->create(['tenant_id' => $this->alpha->id, 'customer_email' => 'real@example.com']);
        QuoteItem::factory()->create(['tenant_id' => $this->alpha->id, 'quote_id' => $quote->id]);
        $sendQuote = $this->prepare(PendingActionEnum::SendQuote, $quote->id);
        $quote->update(['customer_email' => 'attacker@example.com']);

        $this->assertTrue($this->callTool($this->token, 'confirm_action', ['token' => $sendQuote['token']])->isError);
        $this->assertSame(QuoteStatusEnum::Draft, $quote->refresh()->status);

        $expense = Expense::factory()->forTenant($this->alpha)->createdBy($this->admin)->create(['amount' => '10.00']);
        $delete = $this->prepare(PendingActionEnum::DeleteExpense, $expense->id);
        $expense->update(['amount' => '99999.00']);

        $this->webAs($this->admin, $this->alpha)->post(route('pending-actions.confirm', $delete['pending']))->assertSessionHasErrors('status');
        $this->assertNotNull(Expense::query()->find($expense->id));
        $this->assertSame(PendingActionStatusEnum::Cancelled, $delete['pending']->refresh()->status);
    }

    public function test_send_quote_end_to_end_through_confirm_action_with_items(): void
    {
        $quote = Quote::factory()->create(['tenant_id' => $this->alpha->id, 'customer_email' => 'buyer@example.com']);
        QuoteItem::factory()->create(['tenant_id' => $this->alpha->id, 'quote_id' => $quote->id]);
        ['pending' => $pending, 'token' => $token] = $this->prepare(PendingActionEnum::SendQuote, $quote->id);

        $this->assertSame('buyer@example.com', trim((string) $pending->email, '"'));

        $result = $this->callTool($this->token, 'confirm_action', ['token' => $token]);

        $this->assertFalse($result->isError, $result->text);
        $this->assertSame(QuoteStatusEnum::Sent, $quote->refresh()->status);
        $this->assertSame(PendingActionStatusEnum::Executed, $pending->refresh()->status);
    }

    public function test_previews_flatten_and_quote_untrusted_names_and_cannot_reexpand_placeholders(): void
    {
        $invoice = Invoice::factory()->create([
            'tenant_id' => $this->alpha->id,
            'customer_name' => "Evil\n\nTotal: 1.00 EUR :total :email ".str_repeat('x', 200),
            'customer_email' => 'victim@example.com',
            'total' => '500.00',
        ]);

        ['pending' => $pending] = $this->prepare(PendingActionEnum::IssueInvoice, $invoice->id);

        $this->assertStringNotContainsString("\n", $pending->preview);
        $this->assertStringNotContainsString('victim@example.com', $pending->preview);
        $this->assertSame(1, substr_count($pending->preview, '500.00 EUR'));
        $this->assertStringContainsString('Total: 1.00 EUR :total :email', $pending->preview);
        $this->assertLessThan(120, mb_strlen((string) $pending->customer));
        $this->assertSame('500.00 EUR', $pending->total);
        $this->assertSame('"victim@example.com"', $pending->email);

        $this->apiAs($this->admin, $this->alpha)->getJson(route('api.pending-actions.index'))
            ->assertJsonPath('0.total', '500.00 EUR')
            ->assertJsonPath('0.email', '"victim@example.com"');
    }

    public function test_obvious_preconditions_fail_at_prepare_time(): void
    {
        $draft = $this->draftInvoice();
        $issued = Invoice::factory()->issued()->create(['tenant_id' => $this->alpha->id]);

        $this->assertTrue($this->callTool($this->token, 'prepare_action', ['company_id' => $this->alpha->id, 'action' => 'cancel_invoice', 'subject_id' => $draft->id])->isError);
        $this->assertTrue($this->callTool($this->token, 'prepare_action', ['company_id' => $this->alpha->id, 'action' => 'delete_invoice_draft', 'subject_id' => $issued->id])->isError);
        $this->assertSame(0, PendingAction::query()->count());
    }

    public function test_the_web_index_hides_rows_of_firms_the_user_has_left(): void
    {
        ['pending' => $pending] = $this->prepare(PendingActionEnum::IssueInvoice, $this->draftInvoice($this->beta)->id, $this->beta);
        $this->prepare(PendingActionEnum::IssueInvoice, $this->draftInvoice()->id);

        $this->membershipOf($this->admin, $this->beta)->update(['is_active' => false]);

        $this->webAs($this->admin, $this->alpha)->get(route('pending-actions.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page->component('PendingActions/Index', false)->has('pendingActions', 1)->where('pendingActions.0.tenant_id', $this->alpha->id));
        $this->assertSame(PendingActionStatusEnum::Pending, $pending->refresh()->status);
    }

    public function test_same_second_edits_of_any_attribute_are_caught_with_a_frozen_clock(): void
    {
        $this->freezeTime();
        $invoice = $this->draftInvoice();
        ['token' => $token] = $this->prepare(PendingActionEnum::IssueInvoice, $invoice->id);

        foreach (['issue_date' => '2026-01-01', 'delivery_date' => '2026-01-02', 'note' => 'changed', 'customer_street' => 'Elsewhere 1'] as $column => $value) {
            Invoice::query()->whereKey($invoice->id)->update([$column => $value]);

            $this->assertTrue($this->callTool($this->token, 'confirm_action', ['token' => $token])->isError, $column);
            $this->assertSame(InvoiceStatusEnum::Draft, $invoice->refresh()->status, $column);

            ['token' => $token] = $this->prepare(PendingActionEnum::IssueInvoice, $invoice->id);
        }

        $expense = Expense::factory()->forTenant($this->alpha)->createdBy($this->admin)->create(['vat_amount' => '1.00', 'description' => 'a']);

        foreach (['vat_amount' => '9.00', 'category' => 'fuel', 'description' => 'b'] as $column => $value) {
            $row = $this->prepare(PendingActionEnum::DeleteExpense, $expense->id);
            Expense::query()->whereKey($expense->id)->update([$column => $value]);

            $this->webAs($this->admin, $this->alpha)->post(route('pending-actions.confirm', $row['pending']))->assertSessionHasErrors('status');
            $this->assertNotNull(Expense::query()->find($expense->id), $column);
        }
    }

    public function test_a_changed_subject_auto_cancels_the_row_so_no_live_token_or_stale_preview_lingers(): void
    {
        $invoice = $this->draftInvoice();
        ['pending' => $pending, 'token' => $token] = $this->prepare(PendingActionEnum::IssueInvoice, $invoice->id);
        $invoice->update(['note' => 'tampered']);

        $this->assertTrue($this->callTool($this->token, 'confirm_action', ['token' => $token])->isError);

        $pending->refresh();
        $this->assertSame(PendingActionStatusEnum::Cancelled, $pending->status);
        $this->assertNull($pending->token_hash);
        $this->assertSame(0, app(ActionConfirmationService::class)->pendingFor($this->admin)->count());
        $this->assertContains('tool.confirm.failed', $this->aiActivities()->pluck('description')->all());

        $this->webAs($this->admin, $this->alpha)->get(route('pending-actions.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page->component('PendingActions/Index', false)->has('pendingActions', 0));
    }

    public function test_only_user_data_is_quoted_in_previews_not_translated_words(): void
    {
        $invoice = Invoice::factory()->create(['tenant_id' => $this->alpha->id, 'customer_name' => 'Acme', 'customer_email' => null]);

        ['pending' => $pending] = $this->prepare(PendingActionEnum::IssueInvoice, $invoice->id);

        $this->assertStringContainsString('"Acme"', $pending->preview);
        $this->assertStringNotContainsString('"'.__('app.pending_number_draft').'"', $pending->preview);
        $this->assertStringNotContainsString('"'.__('app.pending_number_auto').'"', $pending->preview);
    }

    public function test_the_firm_is_named_in_label_and_preview_without_entering_the_subject_hash(): void
    {
        $invoice = $this->draftInvoice();
        ['pending' => $pending, 'token' => $token] = $this->prepare(PendingActionEnum::IssueInvoice, $invoice->id);

        $this->assertStringContainsString('Alpha', $pending->preview);
        $this->assertStringContainsString('Alpha', $pending->subject_label);

        $this->alpha->update(['name' => 'Alpha Renamed']);

        $this->assertFalse($this->callTool($this->token, 'confirm_action', ['token' => $token])->isError);
        $this->assertSame(InvoiceStatusEnum::Issued, $invoice->refresh()->status);
    }

    public function test_a_deleted_subject_is_the_subject_changed_case_on_mcp_api_and_web(): void
    {
        $viaMcp = $this->draftInvoice();
        $viaApi = $this->draftInvoice();
        $viaWeb = $this->draftInvoice();
        ['pending' => $mcpRow, 'token' => $token] = $this->prepare(PendingActionEnum::IssueInvoice, $viaMcp->id);
        ['pending' => $apiRow] = $this->prepare(PendingActionEnum::IssueInvoice, $viaApi->id);
        ['pending' => $webRow] = $this->prepare(PendingActionEnum::IssueInvoice, $viaWeb->id);

        $viaMcp->delete();
        $viaApi->delete();
        $viaWeb->delete();

        $result = $this->callTool($this->token, 'confirm_action', ['token' => $token]);
        $this->assertTrue($result->isError);
        $this->assertStringContainsString(__('app.pending_action_subject_changed'), $result->text);

        $this->apiAs($this->admin, $this->alpha)->postJson(route('api.pending-actions.confirm', $apiRow))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status')
            ->assertJsonPath('errors.status.0', __('app.pending_action_subject_changed'));

        $this->webAs($this->admin, $this->alpha)->post(route('pending-actions.confirm', $webRow))->assertSessionHasErrors('status');

        foreach ([$mcpRow, $apiRow, $webRow] as $row) {
            $this->assertSame(PendingActionStatusEnum::Cancelled, $row->refresh()->status);
            $this->assertNull($row->token_hash);
        }
    }

    public function test_a_task_run_action_has_no_token_lasts_a_day_and_is_confirmed_in_the_app_only(): void
    {
        $firm = Task::factory()->forTenant($this->alpha)->createdBy($this->admin)->create();

        $prepared = app(ActionConfirmationService::class)->prepare($this->admin, $this->alpha->id, PendingActionEnum::DeleteTask, $firm, [], ActionChannelEnum::TaskRun);

        $this->assertNull($prepared->token);
        $pending = PendingAction::query()->findOrFail($prepared->pending_action->id);
        $this->assertSame(ActionChannelEnum::TaskRun, $pending->channel);
        $this->assertNull($pending->token_hash);
        $this->assertEqualsWithDelta(now()->addDay()->timestamp, $pending->expires_at->timestamp, 5);
        $this->assertSame([ConfirmationChannelEnum::App], $pending->allowedConfirmationChannels());
        $this->assertTrue($this->callTool($this->token, 'confirm_action', ['token' => str_repeat('b', 40)])->isError);

        $this->webAs($this->admin, $this->alpha)->post(route('pending-actions.confirm', $pending))->assertRedirect();
        $this->assertNull(Task::unscopedForSystem()->find($firm->id));
    }

    public function test_mcp_and_assistant_rows_keep_their_ten_minute_window(): void
    {
        $firm = Task::factory()->forTenant($this->alpha)->createdBy($this->admin)->create();

        foreach ([ActionChannelEnum::Mcp, ActionChannelEnum::Assistant] as $channel) {
            $prepared = app(ActionConfirmationService::class)->prepare($this->admin, $this->alpha->id, PendingActionEnum::DeleteTask, $firm, [], $channel);
            $row = PendingAction::query()->findOrFail($prepared->pending_action->id);
            $this->assertEqualsWithDelta(now()->addMinutes(10)->timestamp, $row->expires_at->timestamp, 5, $channel->value);
        }
    }
}
