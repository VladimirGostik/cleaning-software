<?php

declare(strict_types=1);

namespace Tests\Feature\Schedule;

use App\Enums\JobStatusEnum;
use App\Enums\JobTypeEnum;
use App\Models\Activity;
use App\Models\CleaningObject;
use App\Models\Invoice;
use App\Models\ObjectVisitItem;
use App\Models\ScheduledJob;
use App\Models\ScheduledJobItem;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Support\ViewErrorBag;
use Inertia\Testing\AssertableInertia;
use Tests\Support\CreatesWorkRecordFixtures;
use Tests\TestCase;

final class WorkRecordAdminTest extends TestCase
{
    use CreatesWorkRecordFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedPermissionCatalog();
        $this->travelTo(now()->setDate(2026, 11, 13)->setTime(12, 0));
        config(['scheduling.timezone' => 'Europe/Bratislava']);
    }

    /**
     * @param  list<string>  $itemIds
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function adminPayload(string $membershipId, array $itemIds, array $overrides = []): array
    {
        return [
            'tenant_membership_id' => $membershipId,
            'item_ids' => $itemIds,
            'performed_at' => now()->subDays(40)->toIso8601ZuluString(),
            'note' => null,
            ...$overrides,
        ];
    }

    /** @return array{0: Tenant, 1: User, 2: CleaningObject, 3: array<string, ObjectVisitItem>, 4: TenantMembership, 5: ScheduledJob} */
    private function recordFixture(): array
    {
        $firm = $this->firm();
        $admin = $this->member($firm, 'Admin');
        $object = $this->objectIn($firm);
        $items = $this->itemsFor($object, ['Kosenie' => '10.00', 'Zametanie' => '5.50', 'Okna' => '30.00']);
        $worker = $this->membershipWithoutLogin($firm);

        $this->webAs($admin, $firm)->post(route('objects.work-records.store', $object), $this->adminPayload($worker->id, [$items['Kosenie']->id, $items['Zametanie']->id]))->assertRedirect();

        return [$firm, $admin, $object, $items, $worker, ScheduledJob::query()->whereHas('items')->firstOrFail()];
    }

    public function test_admin_adds_a_record_for_an_employee_without_a_login_forty_days_back(): void
    {
        $firm = $this->firm();
        $admin = $this->member($firm, 'Admin');
        $object = $this->objectIn($firm);
        $items = $this->itemsFor($object);
        $worker = $this->membershipWithoutLogin($firm);

        $response = $this->webAs($admin, $firm)->post(route('objects.work-records.store', $object), $this->adminPayload($worker->id, [$items['Kosenie']->id], ['note' => 'Vypomoc']));

        $response->assertRedirect()->assertSessionHas('success', __('app.work_record_created'));
        $job = ScheduledJob::query()->whereHas('items')->firstOrFail();
        $this->assertSame($worker->id, $job->assigned_membership_id);
        $this->assertSame(JobTypeEnum::OneOff, $job->type);
        $this->assertSame(JobStatusEnum::Completed, $job->status);
        $this->assertSame(now()->subDays(40)->setTimezone('Europe/Bratislava')->toDateString(), $job->scheduled_date->toDateString());
        $this->assertSame('Vypomoc', $job->note);
        $this->assertNull($job->client_uuid);
        $this->assertSame(['Kosenie'], $job->items->pluck('name')->all());
    }

    public function test_validation_and_permission(): void
    {
        $firm = $this->firm();
        $admin = $this->member($firm, 'Admin');
        $object = $this->objectIn($firm);
        $items = $this->itemsFor($object);
        $foreign = array_values($this->itemsFor($this->objectIn($firm, 'Other')));
        $worker = $this->membershipWithoutLogin($firm);
        $inactive = $this->membershipWithoutLogin($firm, 'Ina', 'Neaktivna');
        $inactive->update(['is_active' => false]);
        $otherFirm = $this->firm('B');
        $foreignMember = $this->membershipWithoutLogin($otherFirm);
        $post = fn (array $payload) => $this->webAs($admin, $firm)->post(route('objects.work-records.store', $object), $payload);

        $post($this->adminPayload($worker->id, [$items['Kosenie']->id], ['performed_at' => now()->addHour()->toIso8601ZuluString()]))->assertSessionHasErrors('performed_at');
        $post($this->adminPayload($worker->id, [$items['Kosenie']->id], ['performed_at' => 'zajtra']))->assertSessionHasErrors('performed_at');
        $post($this->adminPayload($worker->id, []))->assertSessionHasErrors('item_ids');
        $post($this->adminPayload($worker->id, [$foreign[0]->id]))->assertSessionHasErrors('item_ids');
        $post($this->adminPayload($inactive->id, [$items['Kosenie']->id]))->assertSessionHasErrors('tenant_membership_id');
        $post($this->adminPayload($foreignMember->id, [$items['Kosenie']->id]))->assertSessionHasErrors('tenant_membership_id');
        $this->assertSame(0, ScheduledJob::query()->whereHas('items')->count());

        foreach (['Sekretárka', 'Účtovníčka', 'Interná upratovačka'] as $role) {
            $user = $this->member($firm, $role);
            $this->webAs($user, $firm)->post(route('objects.work-records.store', $object), $this->adminPayload($worker->id, [$items['Kosenie']->id]))->assertForbidden();
        }
    }

    public function test_vedouca_with_the_two_permissions_may_add_and_a_foreign_object_is_not_found(): void
    {
        $firm = $this->firm();
        $lead = $this->member($firm, 'Vedúca');
        $object = $this->objectIn($firm);
        $items = $this->itemsFor($object);
        $worker = $this->membershipWithoutLogin($firm);
        $other = $this->firm('B');
        $foreignObject = $this->objectIn($other, 'X');

        $this->webAs($lead, $firm)->post(route('objects.work-records.store', $object), $this->adminPayload($worker->id, [$items['Kosenie']->id]))->assertRedirect();
        $this->webAs($lead, $firm)->post(route('objects.work-records.store', $foreignObject), $this->adminPayload($worker->id, [$items['Kosenie']->id]))->assertNotFound();
    }

    public function test_a_worker_added_by_the_admin_gains_the_reach_on_the_object(): void
    {
        $firm = $this->firm();
        $admin = $this->member($firm, 'Admin');
        $worker = $this->member($firm, 'Interná upratovačka');
        $object = $this->objectIn($firm);
        $items = $this->itemsFor($object);

        $this->webAs($admin, $firm)->post(route('objects.work-records.store', $object), $this->adminPayload($this->membershipOf($worker, $firm)->id, [$items['Kosenie']->id]))->assertRedirect();

        $this->apiAs($worker, $firm)->postJson('/api/work-records', $this->recordPayload($object, [$items['Kosenie']]))->assertCreated();
    }

    public function test_correct_keeps_the_stored_price_and_an_added_item_takes_the_current_price(): void
    {
        [$firm, $admin, $object, $items, $worker, $job] = $this->recordFixture();
        $items['Kosenie']->update(['price' => '12.00']);
        $kept = $this->itemNamed($job, 'Kosenie');
        $other = $this->membershipWithoutLogin($firm, 'Jan', 'Druhy');

        $response = $this->webAs($admin, $firm)->put(route('jobs.work-record.update', $job), [
            'tenant_membership_id' => $other->id,
            'performed_at' => now()->subDays(3)->toIso8601ZuluString(),
            'note' => 'Opravene',
            'keep_item_ids' => [$kept->id],
            'add_item_ids' => [$items['Okna']->id],
        ]);

        $response->assertRedirect()->assertSessionHas('success', __('app.work_record_updated'));
        $job->refresh();
        $rows = $job->items()->get()->pluck('unit_price', 'name')->all();
        $this->assertSame(['Kosenie' => '10.00', 'Okna' => '30.00'], $rows);
        $this->assertSame($other->id, $job->assigned_membership_id);
        $this->assertSame('Opravene', $job->note);
        $this->assertSame(now()->subDays(3)->setTimezone('Europe/Bratislava')->toDateString(), $job->scheduled_date->toDateString());
        $this->assertSame(now()->subDays(3)->toIso8601ZuluString(), $job->completed_at?->toIso8601ZuluString());
    }

    public function test_the_correction_is_in_the_activity_log_with_items_before_and_after(): void
    {
        [$firm, $admin, , $items, $worker, $job] = $this->recordFixture();
        $kept = $this->itemNamed($job, 'Kosenie');

        $this->webAs($admin, $firm)->put(route('jobs.work-record.update', $job), [
            'tenant_membership_id' => $worker->id,
            'performed_at' => now()->subDays(2)->toIso8601ZuluString(),
            'keep_item_ids' => [$kept->id],
            'add_item_ids' => [$items['Okna']->id],
        ])->assertRedirect();

        $entry = Activity::query()->where('event', 'work_record_corrected')->where('subject_id', $job->id)->firstOrFail();
        $this->assertSame($admin->id, $entry->causer_id);
        $properties = $entry->propertiesArray() ?? [];
        $before = (array) ($properties['items_before'] ?? []);
        $after = (array) ($properties['items_after'] ?? []);
        $this->assertEqualsCanonicalizing(['Kosenie', 'Zametanie'], array_column($before, 'name'));
        $this->assertEqualsCanonicalizing(['Kosenie', 'Okna'], array_column($after, 'name'));
        $this->assertContains('30.00', array_column($after, 'unit_price'));
    }

    public function test_correct_validation(): void
    {
        [$firm, $admin, , $items, $worker, $job] = $this->recordFixture();
        $own = $this->itemNamed($job, 'Kosenie');
        $put = fn (array $overrides) => $this->webAs($admin, $firm)->put(route('jobs.work-record.update', $job), [
            'tenant_membership_id' => $worker->id,
            'performed_at' => now()->subDays(2)->toIso8601ZuluString(),
            'keep_item_ids' => [$own->id],
            'add_item_ids' => [],
            ...$overrides,
        ]);

        $put(['keep_item_ids' => [], 'add_item_ids' => []])->assertSessionHasErrors('keep_item_ids');
        $put(['keep_item_ids' => [(string) Str::uuid()]])->assertSessionHasErrors('keep_item_ids');
        $put(['add_item_ids' => [(string) Str::uuid()]])->assertSessionHasErrors('add_item_ids');
        $put(['add_item_ids' => [$items['Kosenie']->id]])->assertSessionHasErrors('add_item_ids');
        $put(['performed_at' => now()->addDay()->toIso8601ZuluString()])->assertSessionHasErrors('performed_at');
        $put(['performed_at' => 'x'])->assertSessionHasErrors('performed_at');
        $put(['keep_item_ids' => 'x'])->assertSessionHasErrors('keep_item_ids');

        $this->assertSame(2, $job->items()->count());
    }

    public function test_removing_and_adding_the_same_object_item_in_one_correction_works(): void
    {
        [$firm, $admin, , $items, $worker, $job] = $this->recordFixture();
        $zametanie = $this->itemNamed($job, 'Zametanie');
        $items['Zametanie']->update(['price' => '6.00']);

        $this->webAs($admin, $firm)->put(route('jobs.work-record.update', $job), [
            'tenant_membership_id' => $worker->id,
            'performed_at' => now()->subDays(2)->toIso8601ZuluString(),
            'keep_item_ids' => [$this->itemNamed($job, 'Kosenie')->id],
            'add_item_ids' => [$items['Zametanie']->id],
        ])->assertRedirect();

        $this->assertSame('6.00', $job->items()->where('name', 'Zametanie')->firstOrFail()->unit_price);
        $this->assertFalse(ScheduledJobItem::query()->whereKey($zametanie->id)->exists());
    }

    public function test_correct_and_delete_need_edit_schedule_and_a_work_record(): void
    {
        [$firm, , , , $worker, $job] = $this->recordFixture();
        $own = $this->firstItem($job);
        $payload = [
            'tenant_membership_id' => $worker->id,
            'performed_at' => now()->subDays(2)->toIso8601ZuluString(),
            'keep_item_ids' => [$own->id],
            'add_item_ids' => [],
        ];
        $regular = $this->assignedJob($this->member($firm, 'Interná upratovačka'), $firm, $this->objectOfJob($job));

        foreach (['Sekretárka', 'Účtovníčka'] as $role) {
            $user = $this->member($firm, $role);
            $this->webAs($user, $firm)->put(route('jobs.work-record.update', $job), $payload)->assertForbidden();
            $this->webAs($user, $firm)->delete(route('jobs.work-record.destroy', $job))->assertForbidden();
        }

        $cleaner = $this->member($firm, 'Interná upratovačka');
        $this->webAs($cleaner, $firm)->put(route('jobs.work-record.update', $job), $payload)->assertForbidden();
        $this->webAs($cleaner, $firm)->delete(route('jobs.work-record.destroy', $job))->assertForbidden();

        $lead = $this->member($firm, 'Vedúca');
        $this->webAs($lead, $firm)->put(route('jobs.work-record.update', $regular), $payload)->assertForbidden();
        $this->webAs($lead, $firm)->delete(route('jobs.work-record.destroy', $regular))->assertForbidden();
        $this->assertNull($this->reload($job)->deleted_at);
    }

    public function test_an_invoiced_record_cannot_be_corrected_or_deleted(): void
    {
        [$firm, $admin, , , $worker, $job] = $this->recordFixture();
        $job->forceFill(['invoice_id' => Invoice::factory()->create(['tenant_id' => $firm->id])->id])->save();

        $this->webAs($admin, $firm)->put(route('jobs.work-record.update', $job), [
            'tenant_membership_id' => $worker->id,
            'performed_at' => now()->subDays(2)->toIso8601ZuluString(),
            'keep_item_ids' => [$this->firstItem($job)->id],
            'add_item_ids' => [],
        ])->assertForbidden();
        $this->webAs($admin, $firm)->delete(route('jobs.work-record.destroy', $job))->assertForbidden();
        $this->assertNull($this->reload($job)->deleted_at);
        $this->assertSame(2, $job->items()->count());
    }

    public function test_delete_soft_deletes_the_job_and_keeps_the_item_rows(): void
    {
        [$firm, $admin, , , , $job] = $this->recordFixture();

        $this->webAs($admin, $firm)->delete(route('jobs.work-record.destroy', $job))
            ->assertRedirect()->assertSessionHas('success', __('app.work_record_deleted'));

        $this->assertSoftDeleted('cleaning_jobs', ['id' => $job->id]);
        $this->assertSame(2, ScheduledJobItem::query()->where('cleaning_job_id', $job->id)->count());
    }

    public function test_the_job_detail_carries_the_record_with_prices_for_the_office_and_without_for_the_cleaner(): void
    {
        $firm = $this->firm();
        $admin = $this->member($firm, 'Admin');
        $cleaner = $this->member($firm, 'Interná upratovačka');
        $object = $this->objectIn($firm);
        $items = $this->itemsFor($object);
        $this->assignedJob($cleaner, $firm, $object);
        $this->webAs($admin, $firm)->post(route('objects.work-records.store', $object), $this->adminPayload($this->membershipOf($cleaner, $firm)->id, [$items['Kosenie']->id]));
        $job = ScheduledJob::query()->whereHas('items')->firstOrFail();

        $this->webAs($admin, $firm)->get(route('jobs.show', $job))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('job.work_record.items.0.unit_price', '10.00')
            ->where('job.work_record.can_update', true)
            ->where('job.work_record.can_delete', true));

        $this->webAs($cleaner, $firm)->get(route('jobs.show', $job))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('job.work_record.items.0.name', 'Kosenie')
            ->where('job.work_record.items.0.unit_price', null)
            ->where('job.work_record.can_update', false)
            ->where('job.work_record.can_delete', false));
    }

    public function test_a_regular_job_detail_has_no_work_record(): void
    {
        $firm = $this->firm();
        $admin = $this->member($firm, 'Admin');
        $job = $this->assignedJob($this->member($firm, 'Interná upratovačka'), $firm, $this->objectIn($firm));

        $this->webAs($admin, $firm)->get(route('jobs.show', $job))->assertInertia(fn (AssertableInertia $page) => $page->where('job.work_record', null));
    }

    public function test_performed_at_has_a_floor_on_the_web_path(): void
    {
        [$firm, $admin, $object, $items, $worker, $job] = $this->recordFixture();
        $update = fn (string $at) => $this->webAs($admin, $firm)->put(route('jobs.work-record.update', $job), [
            'tenant_membership_id' => $worker->id,
            'performed_at' => $at,
            'keep_item_ids' => [$this->firstItem($job)->id],
            'add_item_ids' => [],
        ]);

        foreach (['0000-00-00T00:00:00Z', '1999-12-31T23:59:59Z'] as $at) {
            $this->webAs($admin, $firm)->post(route('objects.work-records.store', $object), $this->adminPayload($worker->id, [$items['Kosenie']->id], ['performed_at' => $at]))->assertSessionHasErrors('performed_at');
            $update($at)->assertSessionHasErrors('performed_at');
        }

        $update('2000-01-01T00:00:00Z')->assertSessionHasNoErrors();
        $this->assertSame('2000-01-01', $this->reload($job)->scheduled_date->toDateString());
    }

    public function test_upper_case_ids_are_accepted_on_the_web_path(): void
    {
        [$firm, $admin, $object, $items, $worker, $job] = $this->recordFixture();

        $this->webAs($admin, $firm)->post(route('objects.work-records.store', $object), $this->adminPayload($worker->id, [strtoupper($items['Okna']->id)]))->assertSessionHasNoErrors();
        $this->webAs($admin, $firm)->put(route('jobs.work-record.update', $job), [
            'tenant_membership_id' => $worker->id,
            'performed_at' => now()->subDays(2)->toIso8601ZuluString(),
            'keep_item_ids' => [strtoupper($this->itemNamed($job, 'Kosenie')->id)],
            'add_item_ids' => [strtoupper($items['Okna']->id)],
        ])->assertSessionHasNoErrors();

        $this->assertEqualsCanonicalizing(['Kosenie', 'Okna'], $job->items()->pluck('name')->all());
    }

    public function test_a_failing_item_array_rule_never_shows_a_raw_name(): void
    {
        [$firm, $admin, $object, , $worker, $job] = $this->recordFixture();
        $messages = [];

        $this->webAs($admin, $firm)->post(route('objects.work-records.store', $object), $this->adminPayload($worker->id, [], ['item_ids' => 'x']))->assertSessionHasErrors('item_ids');
        $messages = [...$messages, ...$this->sessionMessages('item_ids')];
        $this->webAs($admin, $firm)->post(route('objects.work-records.store', $object), $this->adminPayload($worker->id, array_fill(0, 51, (string) Str::uuid())))->assertSessionHasErrors('item_ids');
        $messages = [...$messages, ...$this->sessionMessages('item_ids')];
        $this->webAs($admin, $firm)->put(route('jobs.work-record.update', $job), ['tenant_membership_id' => $worker->id, 'performed_at' => now()->subDay()->toIso8601ZuluString(), 'keep_item_ids' => 'x', 'add_item_ids' => 'y'])->assertSessionHasErrors(['keep_item_ids', 'add_item_ids']);
        $messages = [...$messages, ...$this->sessionMessages('keep_item_ids'), ...$this->sessionMessages('add_item_ids')];
        $this->webAs($admin, $firm)->put(route('objects.visit-items.update', $object), ['items' => 'x'])->assertSessionHasErrors('items');
        $messages = [...$messages, ...$this->sessionMessages('items')];

        $this->assertNotSame([], $messages);

        foreach ($messages as $message) {
            $this->assertDoesNotMatchRegularExpression('/item_ids|item ids|keep item|add item|\bitems\b/i', $message);
        }
    }

    /** @return list<string> */
    private function sessionMessages(string $key): array
    {
        $bag = session('errors');
        $this->assertInstanceOf(ViewErrorBag::class, $bag);

        return array_values(array_filter((array) $bag->getBag('default')->get($key), is_string(...)));
    }

    public function test_delete_from_the_job_page_goes_to_the_object_or_the_job_list_and_from_the_object_page_stays(): void
    {
        [$firm, $admin, $object, , , $job] = $this->recordFixture();
        $jobPage = route('jobs.show', $job);
        $objectPage = route('objects.show', $object).'?records_month=2026-10';

        $this->webAs($admin, $firm)->from($objectPage)->delete(route('jobs.work-record.destroy', $job))
            ->assertRedirect($objectPage)->assertSessionHas('success', __('app.work_record_deleted'));

        [$firm2, $admin2, $object2, , , $job2] = $this->recordFixture();
        $this->webAs($admin2, $firm2)->from(route('jobs.show', $job2))->delete(route('jobs.work-record.destroy', $job2))
            ->assertRedirect(route('objects.show', $object2))->assertSessionHas('success', __('app.work_record_deleted'));

        $this->assertNotSame($jobPage, route('jobs.show', $job2));
    }

    public function test_delete_from_the_job_page_goes_to_the_job_list_when_the_object_is_not_viewable(): void
    {
        $firm = $this->firm();
        $admin = $this->member($firm, 'Admin');
        $object = $this->objectIn($firm);
        $items = $this->itemsFor($object);
        $worker = $this->membershipWithoutLogin($firm);
        $this->webAs($admin, $firm)->post(route('objects.work-records.store', $object), $this->adminPayload($worker->id, [$items['Kosenie']->id]));
        $job = ScheduledJob::query()->whereHas('items')->firstOrFail();
        $editor = $this->member($firm, 'Vedúca');
        $this->revokeRolePermission($firm, 'Vedúca', 'view objects');
        $editor = User::query()->findOrFail($editor->id);

        $this->webAs($editor, $firm)->from(route('jobs.show', $job))->delete(route('jobs.work-record.destroy', $job))
            ->assertRedirect(route('jobs.index'));
    }

    public function test_the_job_page_carries_the_live_object_items_and_the_source_item_id(): void
    {
        [$firm, $admin, $object, $items, , $job] = $this->recordFixture();
        $items['Zametanie']->forceDelete();

        $this->webAs($admin, $firm)->get(route('jobs.show', $job))->assertInertia(fn (AssertableInertia $page) => $page
            ->has('visitItems', 2)
            ->where('visitItems.0.name', 'Kosenie')
            ->where('job.work_record.items', fn (mixed $rows): bool => $rows instanceof Collection
                && $rows->pluck('object_visit_item_id')->filter()->all() === [$items['Kosenie']->id]
                && $rows->pluck('object_visit_item_id')->contains(null)));

        $regular = $this->assignedJob($this->member($firm, 'Interná upratovačka'), $firm, $object);
        $this->webAs($admin, $firm)->get(route('jobs.show', $regular))->assertInertia(fn (AssertableInertia $page) => $page->where('visitItems', null));

        $cleaner = $this->member($firm, 'Interná upratovačka');
        $this->webAs($admin, $firm)->post(route('objects.work-records.store', $object), $this->adminPayload($this->membershipOf($cleaner, $firm)->id, [$items['Kosenie']->id]));
        $own = ScheduledJob::query()->where('assigned_membership_id', $this->membershipOf($cleaner, $firm)->id)->whereHas('items')->firstOrFail();
        $this->webAs($cleaner, $firm)->get(route('jobs.show', $own))->assertInertia(fn (AssertableInertia $page) => $page->where('visitItems', null));
    }

    public function test_the_record_job_page_carries_member_options_for_the_editor_only(): void
    {
        [$firm, $admin, $object, $items, $worker, $job] = $this->recordFixture();
        $cleaner = $this->member($firm, 'Interná upratovačka');
        $this->webAs($admin, $firm)->post(route('objects.work-records.store', $object), $this->adminPayload($this->membershipOf($cleaner, $firm)->id, [$items['Kosenie']->id]));
        $own = ScheduledJob::query()->where('assigned_membership_id', $this->membershipOf($cleaner, $firm)->id)->whereHas('items')->firstOrFail();

        $this->webAs($admin, $firm)->get(route('jobs.show', $job))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('membershipOptions', fn (mixed $options): bool => $options instanceof Collection && $options->pluck('id')->contains($worker->id)));

        $this->webAs($cleaner, $firm)->get(route('jobs.show', $own))->assertInertia(fn (AssertableInertia $page) => $page->where('membershipOptions', []));
    }
}
