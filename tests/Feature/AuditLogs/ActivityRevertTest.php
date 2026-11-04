<?php

declare(strict_types=1);

namespace Tests\Feature\AuditLogs;

use App\Enums\DocumentTypeEnum;
use App\Enums\InvoiceStatusEnum;
use App\Models\Activity;
use App\Models\CleaningObject;
use App\Models\Client;
use App\Models\Contact;
use App\Models\Contract;
use App\Models\Document;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Quote;
use App\Models\Role;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\TenantInterface;
use App\Models\User;
use App\Services\ActivityRevertService;
use App\Services\ContractService;
use App\Services\QuoteService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;
use Tests\Support\CreatesUsers;
use Tests\TestCase;

final class ActivityRevertTest extends TestCase
{
    use CreatesUsers;
    use RefreshDatabase;

    private Tenant $tenant;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->admin = $this->actingAsTenantUser('Admin', $this->tenant);
    }

    private function row(Model $model, string $event): Activity
    {
        return Activity::query()
            ->where('subject_type', $model->getMorphClass())
            ->where('subject_id', $model->getKey())
            ->where('event', $event)
            ->latest('id')
            ->firstOrFail();
    }

    /** @return TestResponse<Response> */
    private function revert(Activity $activity): TestResponse
    {
        return $this->from('/audit-logs')->post(route('audit-logs.revert', $activity));
    }

    /** @return array<string, mixed> */
    private function detail(Activity $activity): array
    {
        /** @var array{props: array<string, mixed>} $page */
        $page = $this->withoutVite()->get(route('audit-logs.show', $activity))->assertOk()->viewData('page');
        $props = $page['props'];

        /** @var array<string, mixed> */
        return $props['activity'];
    }

    private function reasonOf(Activity $activity): ?string
    {
        /** @var array{reason: ?string} $revert */
        $revert = $this->detail($activity)['revert'];

        return $revert['reason'];
    }

    /** @return array<string, mixed> */
    private function contactPayload(Contact $contact, bool $primary): array
    {
        return ['id' => $contact->id, 'name' => $contact->name, 'position' => null, 'email' => null, 'phone' => null, 'is_primary' => $primary];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function clientPayload(Client $client, array $overrides = []): array
    {
        return array_merge([
            'type' => $client->type->value,
            'name' => $client->name,
            'ico' => $client->ico,
            'dic' => $client->dic,
            'vat_number' => $client->vat_number,
            'is_vat_payer' => $client->is_vat_payer,
            'street' => $client->street,
            'city' => $client->city,
            'postal_code' => $client->postal_code,
            'country' => $client->country,
            'note' => $client->note,
            'contacts' => [],
        ], $overrides);
    }

    public function test_client_update_revert_restores_old_values_and_links_the_batch(): void
    {
        $client = Client::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Old name', 'city' => 'Nitra']);
        $this->patch(route('clients.update', $client), $this->clientPayload($client, ['name' => 'New name', 'city' => 'Zilina']));
        $original = $this->row($client, 'updated');

        $this->assertNotNull($original->batch_uuid);

        $this->revert($original)->assertSessionHas('success')->assertSessionHasNoErrors();

        $client->refresh();
        $this->assertSame('Old name', $client->name);
        $this->assertSame('Nitra', $client->city);

        $original->refresh();
        $this->assertNotNull($original->reverted_at);
        $this->assertNotNull($original->revert_batch_uuid);
        $this->assertGreaterThan(0, Activity::query()->where('batch_uuid', $original->revert_batch_uuid)->where('event', 'updated')->count());
        $this->assertSame(0, Activity::query()->where('batch_uuid', $original->revert_batch_uuid)->whereNotNull('reverted_at')->count());

        $this->revert($original)->assertSessionHasErrors(['status' => __('app.audit_revert_reason_already_reverted')]);
    }

    public function test_revert_of_a_client_edit_with_contact_changes_restores_all_and_keeps_one_primary(): void
    {
        $client = Client::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Before']);
        $first = Contact::factory()->for($client, 'contactable')->create(['tenant_id' => $this->tenant->id, 'is_primary' => true]);
        $second = Contact::factory()->for($client, 'contactable')->create(['tenant_id' => $this->tenant->id, 'is_primary' => false]);

        $this->patch(route('clients.update', $client), $this->clientPayload($client, [
            'name' => 'After',
            'contacts' => [
                $this->contactPayload($second, true),
                ['id' => null, 'name' => 'Added', 'position' => null, 'email' => null, 'phone' => null, 'is_primary' => false],
            ],
        ]));

        $this->assertSoftDeleted('contacts', ['id' => $first->id]);

        $this->revert($this->row($client, 'updated'))->assertSessionHasNoErrors();

        $this->assertSame('Before', $client->refresh()->name);
        $this->assertNull($first->fresh()?->deleted_at);
        $this->assertSoftDeleted('contacts', ['name' => 'Added']);
        $primaries = Contact::query()->where('contactable_id', $client->id)->where('is_primary', true)->pluck('id');
        $this->assertSame([$first->id], $primaries->all());
    }

    public function test_batched_client_delete_revert_restores_everything_and_redo_deletes_again(): void
    {
        $client = Client::factory()->create(['tenant_id' => $this->tenant->id]);
        $contact = Contact::factory()->for($client, 'contactable')->create(['tenant_id' => $this->tenant->id, 'is_primary' => true]);
        $object = CleaningObject::factory()->create(['tenant_id' => $this->tenant->id, 'client_id' => $client->id]);
        $objectContact = Contact::factory()->for($object, 'contactable')->create(['tenant_id' => $this->tenant->id]);

        $this->delete(route('clients.destroy', $client));
        $this->assertSoftDeleted('clients', ['id' => $client->id]);

        $objectRow = $this->row($object, 'deleted');
        /** @var array{batch_size: int} $objectRevert */
        $objectRevert = $this->detail($objectRow)['revert'];
        $this->assertGreaterThan(1, $objectRevert['batch_size']);

        $clientRow = $this->row($client, 'deleted');
        $this->revert($clientRow)->assertSessionHasNoErrors();

        $this->assertNull($client->fresh()?->deleted_at);
        $this->assertNull($object->fresh()?->deleted_at);
        $this->assertNull($contact->fresh()?->deleted_at);
        $this->assertNull($objectContact->fresh()?->deleted_at);
        $this->assertTrue($contact->fresh()?->is_primary);

        $restoredRow = $this->row($client, 'restored');
        /** @var array{reverts_activity_id: ?int} $revertRowInfo */
        $revertRowInfo = $this->detail($restoredRow)['revert'];
        $this->assertSame($clientRow->id, $revertRowInfo['reverts_activity_id']);

        /** @var array{status: string, reverted_by_activity_id: ?int, reverted_by_name: ?string} $originalInfo */
        $originalInfo = $this->detail($clientRow->refresh())['revert'];
        $this->assertSame('reverted', $originalInfo['status']);
        $this->assertSame($restoredRow->id, $originalInfo['reverted_by_activity_id']);
        $this->assertSame($this->admin->name, $originalInfo['reverted_by_name']);

        $this->revert($restoredRow)->assertSessionHasNoErrors();

        $this->assertSoftDeleted('clients', ['id' => $client->id]);
        $this->assertSoftDeleted('objects', ['id' => $object->id]);
        $this->assertSoftDeleted('contacts', ['id' => $contact->id]);
    }

    public function test_stale_update_is_blocked_and_the_detail_marks_the_conflict(): void
    {
        $client = Client::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'One']);
        $client->update(['name' => 'Two']);
        $original = $this->row($client, 'updated');
        $client->update(['name' => 'Three']);

        $this->revert($original)->assertSessionHasErrors(['status' => __('app.audit_revert_reason_conflict')]);
        $this->assertSame('Three', $client->refresh()->name);
        $this->assertNull($original->refresh()->reverted_at);

        $detail = $this->detail($original);
        /** @var list<array{field: string, conflict: bool, current: mixed, old: mixed, new: mixed}> $changes */
        $changes = $detail['changes'];
        $this->assertSame('name', $changes[0]['field']);
        $this->assertTrue($changes[0]['conflict']);
        $this->assertSame('Three', $changes[0]['current']);
        $this->assertSame('One', $changes[0]['old']);
        $this->assertSame('Two', $changes[0]['new']);
    }

    public function test_create_revert_is_blocked_by_later_changes_but_works_on_a_clean_row(): void
    {
        $edited = Task::factory()->forTenant($this->tenant)->create();
        $edited->update(['title' => 'Edited later']);
        $clean = Task::factory()->forTenant($this->tenant)->create();

        $this->revert($this->row($edited, 'created'))
            ->assertSessionHasErrors(['status' => __('app.audit_revert_reason_has_later_changes')]);
        $this->assertNull($edited->fresh()?->deleted_at);

        $this->revert($this->row($clean, 'created'))->assertSessionHasNoErrors();
        $this->assertSoftDeleted('tasks', ['id' => $clean->id]);
    }

    public function test_draft_invoice_create_revert_deletes_it_and_an_issued_one_is_not_draft(): void
    {
        $draft = Invoice::factory()->create(['tenant_id' => $this->tenant->id]);
        $issued = Invoice::factory()->create(['tenant_id' => $this->tenant->id]);
        $issued->update(['status' => InvoiceStatusEnum::Issued]);

        $this->revert($this->row($draft, 'created'))->assertSessionHasNoErrors();
        $this->assertSoftDeleted('invoices', ['id' => $draft->id]);

        $this->revert($this->row($issued, 'created'))->assertSessionHasErrors(['status' => __('app.audit_revert_reason_not_draft')]);
        $this->assertNull($issued->fresh()?->deleted_at);
    }

    public function test_deleted_draft_quote_is_restored_with_its_document(): void
    {
        $quote = Quote::factory()->document()->create(['tenant_id' => $this->tenant->id]);
        $document = Document::factory()->create([
            'tenant_id' => $this->tenant->id,
            'documentable_type' => $quote->getMorphClass(),
            'documentable_id' => $quote->id,
            'type' => DocumentTypeEnum::Quote,
        ]);

        app(QuoteService::class)->delete($quote->refresh());
        $this->assertSoftDeleted('quotes', ['id' => $quote->id]);
        $this->assertSoftDeleted('documents', ['id' => $document->id]);

        $this->revert($this->row($quote, 'deleted'))->assertSessionHasNoErrors();

        $this->assertNull($quote->fresh()?->deleted_at);
        $this->assertNull($document->fresh()?->deleted_at);
    }

    public function test_status_updates_are_lifecycle_fields_and_other_rows_are_unsupported(): void
    {
        $task = Task::factory()->forTenant($this->tenant)->create();
        $task->update(['status' => 'done']);
        $expense = Expense::factory()->forTenant($this->tenant)->draft()->create();
        $expense->update(['status' => 'confirmed', 'category' => 'other', 'confirmed_at' => now()]);
        $invoice = Invoice::factory()->create(['tenant_id' => $this->tenant->id]);
        $invoice->update(['status' => InvoiceStatusEnum::Issued]);

        $this->assertSame('lifecycle_field', $this->reasonOf($this->row($task, 'updated')));
        $this->assertSame('lifecycle_field', $this->reasonOf($this->row($expense, 'updated')));
        $this->assertSame('lifecycle_field', $this->reasonOf($this->row($invoice, 'updated')));

        activity('auth')->causedBy($this->admin)->event('login')->log('login');
        $login = Activity::query()->latest('id')->firstOrFail();
        $this->assertSame('unsupported_operation', $this->reasonOf($login));

        $role = Role::query()->where('tenant_id', $this->tenant->id)->firstOrFail();
        activity()->performedOn($role)->event('updated')->log('role');
        $roleRow = Activity::query()->latest('id')->firstOrFail();
        $roleRow->update(['attribute_changes' => ['attributes' => ['name' => 'x'], 'old' => ['name' => 'y']]]);
        $this->assertSame('unsupported_model', $this->reasonOf($roleRow));
    }

    public function test_expense_update_revert_works_only_while_draft(): void
    {
        $expense = Expense::factory()->forTenant($this->tenant)->draft()->create(['supplier_name' => 'Before']);
        $expense->update(['supplier_name' => 'After']);
        $row = $this->row($expense, 'updated');

        $expense->update(['status' => 'confirmed', 'category' => 'other', 'confirmed_at' => now()]);
        $this->revert($row)->assertSessionHasErrors(['status' => __('app.audit_revert_reason_not_draft')]);

        $expense->update(['status' => 'draft', 'confirmed_at' => null]);
        $this->revert($row)->assertSessionHasNoErrors();
        $this->assertSame('Before', $expense->refresh()->supplier_name);
    }

    public function test_contact_restore_is_blocked_while_its_client_is_trashed(): void
    {
        $client = Client::factory()->create(['tenant_id' => $this->tenant->id]);
        $contact = Contact::factory()->for($client, 'contactable')->create(['tenant_id' => $this->tenant->id]);
        $contact->delete();
        $client->delete();

        $row = $this->row($contact, 'deleted');
        $this->assertSame('parent_deleted', $this->reasonOf($row));
        $this->revert($row)->assertSessionHasErrors(['status' => __('app.audit_revert_reason_parent_deleted')]);
    }

    public function test_primary_contact_create_cannot_be_reverted_alone(): void
    {
        $client = Client::factory()->create(['tenant_id' => $this->tenant->id]);
        $primary = Contact::factory()->for($client, 'contactable')->create(['tenant_id' => $this->tenant->id, 'is_primary' => true]);

        $row = $this->row($primary, 'created');
        $this->assertSame('primary_contact', $this->reasonOf($row));
        $this->revert($row)->assertSessionHasErrors(['status' => __('app.audit_revert_reason_primary_contact')]);
    }

    public function test_restore_clashing_with_an_active_unique_value_is_reported(): void
    {
        $gone = Client::factory()->create(['tenant_id' => $this->tenant->id, 'ico' => '12345678']);
        $gone->delete();
        Client::factory()->create(['tenant_id' => $this->tenant->id, 'ico' => '12345678']);

        $this->revert($this->row($gone, 'deleted'))->assertSessionHasErrors(['status' => __('app.audit_revert_reason_unique_conflict')]);
        $this->assertNotNull(Client::withTrashed()->find($gone->id)?->deleted_at);
    }

    public function test_batch_member_blocked_names_the_blocking_row(): void
    {
        $client = Client::factory()->create(['tenant_id' => $this->tenant->id]);
        $contact = Contact::factory()->for($client, 'contactable')->create(['tenant_id' => $this->tenant->id]);
        $this->delete(route('clients.destroy', $client));

        Contact::withTrashed()->find($contact->id)?->restore();

        $clientRow = $this->row($client, 'deleted');
        /** @var array{reason: ?string, blocking_activity_id: ?int} $info */
        $info = $this->detail($clientRow)['revert'];

        $this->assertSame('batch_member_blocked', $info['reason']);
        $this->assertSame($this->row($contact, 'deleted')->id, $info['blocking_activity_id']);
    }

    public function test_user_without_the_revert_permission_gets_403(): void
    {
        $user = $this->userWithPermission('view audit logs', 'edit clients');
        $client = Client::factory()->create(['tenant_id' => current_tenant_id()]);
        $client->update(['name' => 'Changed']);

        $this->actingAs($user)->post(route('audit-logs.revert', $this->row($client, 'updated')))->assertForbidden();
    }

    public function test_user_without_the_record_ability_gets_403_and_forbidden_reason(): void
    {
        $user = $this->userWithPermission('view audit logs', 'revert audit logs', 'view clients');
        $client = Client::factory()->create(['tenant_id' => current_tenant_id()]);
        $client->update(['name' => 'Changed']);
        $row = $this->row($client, 'updated');

        $this->actingAs($user)->post(route('audit-logs.revert', $row))->assertForbidden();
        $this->assertSame('forbidden', $this->reasonOf($row));
        $this->assertSame('Changed', $client->fresh()?->name);
    }

    public function test_activity_of_another_tenant_and_null_tenant_rows_are_not_revertable(): void
    {
        $foreign = Tenant::factory()->create();
        $this->bindTenant($foreign);
        $foreignClient = Client::factory()->create(['tenant_id' => $foreign->id]);
        $foreignClient->update(['name' => 'Changed']);
        $foreignRow = $this->row($foreignClient, 'updated');
        $this->bindTenant($this->tenant);

        $this->post(route('audit-logs.revert', $foreignRow))->assertForbidden();

        activity()->event('updated')->log('no tenant');
        $nullRow = Activity::query()->latest('id')->firstOrFail();
        $nullRow->update(['tenant_id' => null]);

        $this->post(route('audit-logs.revert', $nullRow))->assertForbidden();
        $this->assertSame('no_company', app(ActivityRevertService::class)->describe($nullRow, $this->admin)->reason?->value);
    }

    public function test_redo_of_a_quote_and_a_contract_revert_ignores_the_document_satellite_rows(): void
    {
        $quote = Quote::factory()->document()->create(['tenant_id' => $this->tenant->id]);
        $quoteDocument = Document::factory()->create([
            'tenant_id' => $this->tenant->id,
            'documentable_type' => $quote->getMorphClass(),
            'documentable_id' => $quote->id,
            'type' => DocumentTypeEnum::Quote,
        ]);
        $contract = Contract::factory()->create(['tenant_id' => $this->tenant->id]);
        $contractDocument = $contract->document()->firstOrFail();

        app(QuoteService::class)->delete($quote->refresh());
        app(ContractService::class)->delete($contract->refresh());

        $this->revert($this->row($quote, 'deleted'))->assertSessionHasNoErrors();
        $this->revert($this->row($contract, 'deleted'))->assertSessionHasNoErrors();
        $this->assertNull($quoteDocument->fresh()?->deleted_at);
        $this->assertNull($contractDocument->fresh()?->deleted_at);

        $this->revert($this->row($quote, 'restored'))->assertSessionHasNoErrors();
        $this->revert($this->row($contract, 'restored'))->assertSessionHasNoErrors();

        $this->assertSoftDeleted('quotes', ['id' => $quote->id]);
        $this->assertSoftDeleted('documents', ['id' => $quoteDocument->id]);
        $this->assertSoftDeleted('contracts', ['id' => $contract->id]);
        $this->assertSoftDeleted('documents', ['id' => $contractDocument->id]);
    }

    public function test_sole_contact_removed_by_an_edit_is_restored_as_primary(): void
    {
        $client = Client::factory()->create(['tenant_id' => $this->tenant->id]);
        $contact = Contact::factory()->for($client, 'contactable')->create(['tenant_id' => $this->tenant->id, 'is_primary' => true]);
        $contact->delete();

        $this->revert($this->row($contact, 'deleted'))->assertSessionHasNoErrors();

        $this->assertTrue((bool) $contact->fresh()?->is_primary);
    }

    public function test_contact_restored_while_another_primary_exists_becomes_non_primary(): void
    {
        $client = Client::factory()->create(['tenant_id' => $this->tenant->id]);
        $old = Contact::factory()->for($client, 'contactable')->create(['tenant_id' => $this->tenant->id, 'is_primary' => true]);
        $old->delete();
        Contact::factory()->for($client, 'contactable')->create(['tenant_id' => $this->tenant->id, 'is_primary' => true]);

        $this->revert($this->row($old, 'deleted'))->assertSessionHasNoErrors();

        $this->assertFalse((bool) $old->fresh()?->is_primary);
    }

    public function test_foreign_tenant_subject_is_neither_labelled_nor_exposed(): void
    {
        $foreign = Tenant::factory()->create(['name' => 'Foreign Secret s.r.o.', 'iban' => 'SK0000000000000000000001']);
        activity()->event('created')->performedOn($foreign)->withProperties([])->log('created');
        $row = Activity::query()->latest('id')->firstOrFail();
        $row->update(['attribute_changes' => ['attributes' => ['name' => 'x', 'iban' => 'y']]]);
        $service = app(ActivityRevertService::class);

        $this->assertNull($service->subjectLabels(collect([$row]))[$row->id]);
        $row = Activity::query()->findOrFail($row->id);
        foreach ($service->changes($row) as $change) {
            $this->assertNull($change->current);
        }
    }

    public function test_tenant_and_invoicing_settings_updates_can_be_reverted(): void
    {
        $this->tenant->update(['invoice_number_format' => 'FA-{YYYY}-{XXXX}']);
        $this->tenant->update(['name' => 'Renamed firm']);
        $original = $this->row($this->tenant, 'updated');
        $settings = TenantInterface::query()->where('tenant_id', $this->tenant->id)->firstOrFail();
        $settings->update(['default_header_text' => 'Header']);
        $settingsRow = $this->row($settings, 'updated');

        $this->revert($original)->assertSessionHasNoErrors();
        $this->revert($settingsRow)->assertSessionHasNoErrors();

        $this->assertNotSame('Renamed firm', $this->tenant->refresh()->name);
        $this->assertNull($settings->refresh()->default_header_text);
    }

    public function test_non_admin_with_revert_and_edit_clients_can_revert(): void
    {
        $user = $this->userWithPermission('view audit logs', 'revert audit logs', 'edit clients');
        $client = Client::factory()->create(['tenant_id' => current_tenant_id(), 'name' => 'Before']);
        $client->update(['name' => 'After']);

        $this->actingAs($user)->from('/audit-logs')->post(route('audit-logs.revert', $this->row($client, 'updated')))
            ->assertSessionHasNoErrors();

        $this->assertSame('Before', $client->refresh()->name);
    }

    public function test_index_runs_a_fixed_number_of_queries_regardless_of_row_count(): void
    {
        $this->seedMixedRows(1);
        $this->indexQueryCount();
        $few = $this->indexQueryCount();

        $this->seedMixedRows(4);
        $many = $this->indexQueryCount();

        $this->assertSame($few, $many);
    }

    private function seedMixedRows(int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            $client = Client::factory()->create(['tenant_id' => $this->tenant->id]);
            $client->update(['name' => 'Renamed '.$i]);
            Contact::factory()->for($client, 'contactable')->create(['tenant_id' => $this->tenant->id]);
            $object = CleaningObject::factory()->create(['tenant_id' => $this->tenant->id, 'client_id' => $client->id]);
            Contact::factory()->for($object, 'contactable')->create(['tenant_id' => $this->tenant->id]);
            Task::factory()->forTenant($this->tenant)->create();
            Expense::factory()->forTenant($this->tenant)->create();
        }
    }

    private function indexQueryCount(): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->withoutVite()->get(route('audit-logs.index', ['per_page' => 100]))->assertOk();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }
}
