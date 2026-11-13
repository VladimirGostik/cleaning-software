<?php

declare(strict_types=1);

namespace Tests\Feature\Schedule;

use App\Enums\JobStatusEnum;
use App\Models\CleaningObject;
use App\Models\ObjectVisitItem;
use App\Models\ScheduledJob;
use App\Models\Tenant;
use App\Models\User;
use App\Services\WorkRecordService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia;
use Tests\Support\CreatesWorkRecordFixtures;
use Tests\TestCase;

final class WorkRecordOverviewTest extends TestCase
{
    use CreatesWorkRecordFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedPermissionCatalog();
        Storage::fake('local');
        config(['scheduling.work_records.photos.disk' => 'local', 'scheduling.timezone' => 'Europe/Bratislava']);
        $this->travelTo(now()->setDate(2026, 11, 13)->setTime(12, 0));
    }

    /** @param list<ObjectVisitItem> $items */
    private function record(User $cleaner, Tenant $firm, CleaningObject $object, array $items, string $at): ScheduledJob
    {
        $id = $this->apiAs($cleaner, $firm)->postJson('/api/work-records', $this->recordPayload($object, $items, ['performed_at' => $at]))->assertCreated()->json('id');

        return ScheduledJob::query()->whereKey($id)->firstOrFail();
    }

    /** @return TestResponse<Response> */
    private function overview(User $user, Tenant $firm, CleaningObject $object, string $month = '2026-11'): TestResponse
    {
        $this->consoleContext();

        return $this->withHeaders(['X-Tenant-Id' => $firm->id])->webAs($user, $firm)->get(route('objects.show', ['object' => $object, 'records_month' => $month]));
    }

    public function test_summary_groups_by_name_and_price_and_totals_the_month(): void
    {
        $firm = $this->firm();
        $admin = $this->member($firm, 'Admin');
        $cleaner = $this->member($firm, 'Interná upratovačka');
        $object = $this->objectIn($firm);
        $items = $this->itemsFor($object, ['Kosenie' => '10.00', 'Zametanie' => '5.50']);
        $this->assignedJob($cleaner, $firm, $object);
        $this->record($cleaner, $firm, $object, [$items['Kosenie'], $items['Zametanie']], '2026-11-10T08:00:00Z');
        $this->record($cleaner, $firm, $object, [$items['Kosenie']], '2026-11-11T08:00:00Z');
        $items['Kosenie']->update(['price' => '12.00']);
        $this->record($cleaner, $firm, $object, [$items['Kosenie']], '2026-11-12T08:00:00Z');

        $this->overview($admin, $firm, $object)->assertInertia(fn (AssertableInertia $page) => $page
            ->where('visitRecords.month', '2026-11')
            ->has('visitRecords.summary', 3)
            ->where('visitRecords.summary.0', ['name' => 'Kosenie', 'unit_price' => '10.00', 'count' => 2, 'total' => '20.00'])
            ->where('visitRecords.summary.1', ['name' => 'Kosenie', 'unit_price' => '12.00', 'count' => 1, 'total' => '12.00'])
            ->where('visitRecords.summary.2', ['name' => 'Zametanie', 'unit_price' => '5.50', 'count' => 1, 'total' => '5.50'])
            ->where('visitRecords.total', '37.50')
            ->has('visitRecords.records', 3));
    }

    public function test_records_carry_time_person_items_note_and_photos_newest_first(): void
    {
        $firm = $this->firm();
        $admin = $this->member($firm, 'Admin');
        $cleaner = $this->member($firm, 'Interná upratovačka');
        $object = $this->objectIn($firm);
        $items = $this->itemsFor($object);
        $this->assignedJob($cleaner, $firm, $object);
        $older = $this->record($cleaner, $firm, $object, [$items['Kosenie']], '2026-11-10T08:00:00Z');
        $newer = $this->record($cleaner, $firm, $object, [$items['Kosenie'], $items['Zametanie']], '2026-11-12T08:00:00Z');
        $newer->update(['note' => 'Poznamka']);
        $this->apiAs($cleaner, $firm)->post("/api/jobs/{$newer->id}/photos", ['file' => UploadedFile::fake()->image('a.jpg'), 'client_uuid' => (string) Str::uuid()], ['Accept' => 'application/json'])->assertCreated();
        $membership = $this->membershipOf($cleaner, $firm);

        $this->overview($admin, $firm, $object)->assertInertia(fn (AssertableInertia $page) => $page
            ->where('visitRecords.records.0.job_id', $newer->id)
            ->where('visitRecords.records.1.job_id', $older->id)
            ->where('visitRecords.records.0.performed_at', '2026-11-12T08:00:00Z')
            ->where('visitRecords.records.0.tenant_membership_id', $membership->id)
            ->where('visitRecords.records.0.recorded_by_name', $cleaner->name)
            ->where('visitRecords.records.0.note', 'Poznamka')
            ->has('visitRecords.records.0.items', 2)
            ->where('visitRecords.records.0.items.0.unit_price', '10.00')
            ->has('visitRecords.records.0.photos', 1)
            ->where('visitRecords.records.0.photos.0.url', fn (string $url): bool => str_starts_with($url, '/jobs/'.$newer->id.'/photos/'))
            ->where('visitRecords.records.0.can_update', true)
            ->where('visitRecords.records.0.can_delete', true)
            ->has('visitRecords.records.1.photos', 0));
    }

    public function test_the_month_follows_the_local_day_and_other_objects_and_regular_jobs_are_excluded(): void
    {
        $firm = $this->firm();
        $admin = $this->member($firm, 'Admin');
        $cleaner = $this->member($firm, 'Interná upratovačka');
        $object = $this->objectIn($firm);
        $other = $this->objectIn($firm, 'Other');
        $items = $this->itemsFor($object);
        $otherItems = $this->itemsFor($other);
        $this->assignedJob($cleaner, $firm, $object);
        $this->assignedJob($cleaner, $firm, $other);
        $this->assignedJob($cleaner, $firm, $object, JobStatusEnum::Completed, '2026-11-05');
        $this->travelTo(now()->setDate(2026, 11, 2)->setTime(12, 0));
        $this->record($cleaner, $firm, $object, [$items['Kosenie']], '2026-10-31T23:30:00Z');
        $this->travelTo(now()->setDate(2026, 12, 2)->setTime(12, 0));
        $this->record($cleaner, $firm, $object, [$items['Kosenie']], '2026-11-30T23:30:00Z');
        $this->record($cleaner, $firm, $object, [$items['Kosenie']], '2026-11-30T22:30:00Z');
        $this->record($cleaner, $firm, $other, [$otherItems['Kosenie']], '2026-11-30T20:00:00Z');

        $this->overview($admin, $firm, $object, '2026-11')->assertInertia(fn (AssertableInertia $page) => $page
            ->where('visitRecords.summary.0.count', 2)
            ->has('visitRecords.records', 2));
        $this->overview($admin, $firm, $object, '2026-12')->assertInertia(fn (AssertableInertia $page) => $page->has('visitRecords.records', 1));
        $this->overview($admin, $firm, $object, '2026-10')->assertInertia(fn (AssertableInertia $page) => $page->has('visitRecords.records', 0)->where('visitRecords.total', '0.00'));
    }

    public function test_a_deleted_record_is_no_longer_counted(): void
    {
        $firm = $this->firm();
        $admin = $this->member($firm, 'Admin');
        $cleaner = $this->member($firm, 'Interná upratovačka');
        $object = $this->objectIn($firm);
        $items = $this->itemsFor($object);
        $this->assignedJob($cleaner, $firm, $object);
        $kept = $this->record($cleaner, $firm, $object, [$items['Kosenie']], '2026-11-10T08:00:00Z');
        $gone = $this->record($cleaner, $firm, $object, [$items['Kosenie']], '2026-11-11T08:00:00Z');
        $this->consoleContext();
        $this->webAs($admin, $firm)->delete(route('jobs.work-record.destroy', $gone))->assertRedirect();

        $this->overview($admin, $firm, $object)->assertInertia(fn (AssertableInertia $page) => $page
            ->where('visitRecords.summary.0.count', 1)
            ->where('visitRecords.total', '10.00')
            ->where('visitRecords.records.0.job_id', $kept->id)
            ->has('visitRecords.records', 1));
    }

    public function test_can_add_and_members_follow_create_schedule_and_assign_cleaners(): void
    {
        $firm = $this->firm();
        $object = $this->objectIn($firm);
        $this->itemsFor($object);
        $worker = $this->membershipWithoutLogin($firm);
        $this->member($firm, 'Interná upratovačka');

        $this->overview($this->member($firm, 'Admin'), $firm, $object)->assertInertia(fn (AssertableInertia $page) => $page
            ->where('visitRecords.can_add', true)
            ->where('visitRecords.members', fn (mixed $members): bool => $members instanceof Collection && $members->pluck('id')->contains($worker->id)));

        $this->overview($this->member($firm, 'Sekretárka'), $firm, $object)->assertInertia(fn (AssertableInertia $page) => $page
            ->where('visitRecords.can_add', false)
            ->where('visitRecords.members', []));
    }

    public function test_the_overview_and_the_item_prices_are_null_without_view_all_objects(): void
    {
        $firm = $this->firm();
        $cleaner = $this->member($firm, 'Interná upratovačka');
        $object = $this->objectIn($firm);
        $items = $this->itemsFor($object);
        $this->assignedJob($cleaner, $firm, $object);
        $this->record($cleaner, $firm, $object, [$items['Kosenie']], '2026-11-10T08:00:00Z');

        $response = $this->overview($cleaner, $firm, $object);

        $response->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->where('visitRecords', null)->where('visitItems', null));
        $this->assertStringNotContainsString('10.00', (string) $response->getContent());
    }

    public function test_overview_service_hides_prices_for_an_actor_without_view_all_objects(): void
    {
        $firm = $this->firm();
        $cleaner = $this->member($firm, 'Interná upratovačka');
        $object = $this->objectIn($firm);
        $items = $this->itemsFor($object);
        $this->assignedJob($cleaner, $firm, $object);
        $job = $this->record($cleaner, $firm, $object, [$items['Kosenie']], '2026-11-10T08:00:00Z');
        $this->consoleContext();
        app()->instance('current_tenant_id', $firm->id);
        $cleaner = User::query()->findOrFail($cleaner->id);

        $present = app(WorkRecordService::class)->present($job->load('items'), $cleaner);

        $this->assertNull($present->items[0]->unit_price);
        $this->assertSame('Kosenie', $present->items[0]->name);
        $this->assertFalse($present->can_update);
    }

    public function test_another_firm_cannot_see_the_records(): void
    {
        $a = $this->firm('A');
        $b = $this->firm('B');
        $adminB = $this->member($b, 'Admin');
        $cleaner = $this->member($a, 'Interná upratovačka');
        $object = $this->objectIn($a);
        $items = $this->itemsFor($object);
        $this->assignedJob($cleaner, $a, $object);
        $this->record($cleaner, $a, $object, [$items['Kosenie']], '2026-11-10T08:00:00Z');

        $this->overview($adminB, $b, $object)->assertNotFound();
    }
}
