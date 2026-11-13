<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Enums\JobStatusEnum;
use App\Models\CleaningObject;
use App\Models\Client;
use App\Models\ScheduledJob;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkBreakdown;
use App\Models\WorkBreakdownTask;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesWorkRecordFixtures;
use Tests\TestCase;

final class MyJobsApiTest extends TestCase
{
    use CreatesWorkRecordFixtures, RefreshDatabase;

    private function objectIn(Tenant $tenant, string $name = 'Object', bool $trashed = false): CleaningObject
    {
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $object = CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id, 'name' => $name, 'street' => 'Hlavna 1', 'city' => 'Bratislava']);

        if ($trashed) {
            $object->delete();
        }

        return $object;
    }

    private function jobFor(User $user, Tenant $tenant, CleaningObject $object, string $date, JobStatusEnum $status = JobStatusEnum::Planned): ScheduledJob
    {
        return ScheduledJob::factory()->assignedTo($this->membershipOf($user, $tenant))->forObject($object)
            ->state(['status' => $status])->create(['tenant_id' => $tenant->id, 'scheduled_date' => $date]);
    }

    public function test_returns_own_jobs_from_all_firms_ordered_without_cancelled_or_foreign_ones(): void
    {
        $this->seedPermissionCatalog();
        $a = $this->firm('A');
        $b = $this->firm('B');
        $user = $this->member($a, 'Interná upratovačka');
        $this->member($b, 'Interná upratovačka', $user);
        $other = $this->member($a, 'Interná upratovačka');

        $later = $this->jobFor($user, $b, $this->objectIn($b, 'Later'), '2026-11-12');
        $first = $this->jobFor($user, $a, $this->objectIn($a, 'First'), '2026-11-10');
        $this->jobFor($user, $a, $this->objectIn($a), '2026-11-11', JobStatusEnum::Cancelled);
        $this->jobFor($other, $a, $this->objectIn($a), '2026-11-11');
        $this->jobFor($user, $a, $this->objectIn($a), '2026-12-30');

        $response = $this->apiAs($user, $a)->getJson('/api/my-jobs?from=2026-11-01&to=2026-11-30');

        $response->assertOk();
        /** @var list<array<string, mixed>> $rows */
        $rows = $response->json();
        $this->assertSame([$first->id, $later->id], array_column($rows, 'id'));
        $response->assertJsonPath('0.tenant_id', $a->id);
        $response->assertJsonPath('0.object_name', 'First');
        $response->assertJsonPath('0.object_street', 'Hlavna 1');
        $response->assertJsonPath('1.tenant_id', $b->id);
        $this->assertArrayNotHasKey('price', $rows[0]);
    }

    public function test_soft_deleted_object_still_renders(): void
    {
        $this->seedPermissionCatalog();
        $a = $this->firm('A');
        $user = $this->member($a, 'Interná upratovačka');
        $this->jobFor($user, $a, $this->objectIn($a, 'Gone', trashed: true), '2026-11-10');

        $this->apiAs($user, $a)->getJson('/api/my-jobs?from=2026-11-01&to=2026-11-30')
            ->assertOk()
            ->assertJsonPath('0.object_name', 'Gone');
    }

    public function test_task_name_comes_from_the_work_breakdown_task(): void
    {
        $this->seedPermissionCatalog();
        $a = $this->firm('A');
        $user = $this->member($a, 'Interná upratovačka');
        $object = $this->objectIn($a);
        $breakdown = WorkBreakdown::factory()->create(['tenant_id' => $a->id, 'cleaning_object_id' => $object->id]);
        $task = WorkBreakdownTask::factory()->create(['tenant_id' => $a->id, 'work_breakdown_id' => $breakdown->id, 'name' => 'Vysavanie']);
        $job = $this->jobFor($user, $a, $object, '2026-11-10');
        $job->forceFill(['work_breakdown_id' => $task->work_breakdown_id, 'work_breakdown_task_id' => $task->id])->save();

        $this->apiAs($user, $a)->getJson('/api/my-jobs?from=2026-11-01&to=2026-11-30')
            ->assertOk()
            ->assertJsonPath('0.task_name', 'Vysavanie');
    }

    public function test_firm_without_view_schedule_is_left_out(): void
    {
        $this->seedPermissionCatalog();
        $a = $this->firm('A');
        $b = $this->firm('B');
        $user = $this->member($a, 'Interná upratovačka');
        $this->member($b, 'Účtovníčka', $user);
        $this->jobFor($user, $a, $this->objectIn($a), '2026-11-10');
        $this->jobFor($user, $b, $this->objectIn($b), '2026-11-10');

        $response = $this->apiAs($user, $b)->getJson('/api/my-jobs?from=2026-11-01&to=2026-11-30');

        $response->assertOk()->assertJsonCount(1);
        $response->assertJsonPath('0.tenant_id', $a->id);
    }

    public function test_forbidden_when_no_firm_grants_view_schedule(): void
    {
        $this->seedPermissionCatalog();
        $b = $this->firm('B');
        $user = $this->member($b, 'Účtovníčka');

        $this->apiAs($user, $b)->getJson('/api/my-jobs?from=2026-11-01&to=2026-11-30')->assertForbidden();
    }

    public function test_can_complete_follows_permission_and_day_in_the_configured_timezone(): void
    {
        $this->seedPermissionCatalog();
        $this->travelTo(now()->setDate(2026, 11, 10)->setTime(23, 30));
        config(['scheduling.timezone' => 'Europe/Bratislava']);
        $a = $this->firm('A');
        $user = $this->member($a, 'Interná upratovačka');
        $object = $this->objectIn($a);
        $this->jobFor($user, $a, $object, '2026-11-11');
        $this->jobFor($user, $a, $object, '2026-11-12');

        $response = $this->apiAs($user, $a)->getJson('/api/my-jobs?from=2026-11-01&to=2026-11-30');

        $response->assertJsonPath('0.scheduled_date', '2026-11-11');
        $response->assertJsonPath('0.can_complete', true);
        $response->assertJsonPath('1.can_complete', false);
    }

    public function test_span_over_62_days_is_422(): void
    {
        $this->seedPermissionCatalog();
        $a = $this->firm('A');
        $user = $this->member($a, 'Interná upratovačka');

        $this->apiAs($user, $a)->getJson('/api/my-jobs?from=2026-01-01&to=2026-03-10')->assertUnprocessable()->assertJsonValidationErrors('to');
        $this->apiAs($user, $a)->getJson('/api/my-jobs?from=2026-01-01&to=2026-03-04')->assertOk();
        $this->apiAs($user, $a)->getJson('/api/my-jobs?from=2026-03-04&to=2026-01-01')->assertUnprocessable()->assertJsonValidationErrors('to');
        $this->apiAs($user, $a)->getJson('/api/my-jobs')->assertUnprocessable()->assertJsonValidationErrors(['from', 'to']);
    }

    public function test_firm_with_view_schedule_but_no_mobile_access_is_left_out(): void
    {
        $this->seedPermissionCatalog();
        $a = $this->firm('A');
        $b = $this->firm('B');
        $user = $this->member($a, 'Interná upratovačka');
        $this->member($b, 'Zákazník', $user);
        $this->jobFor($user, $a, $this->objectIn($a), '2026-11-10');
        $this->jobFor($user, $b, $this->objectIn($b), '2026-11-10');
        $this->app['auth']->forgetGuards();
        Sanctum::actingAs($user, ['mobile']);

        $response = $this->withHeaders(['X-Tenant-Id' => $a->id])->getJson('/api/my-jobs?from=2026-11-01&to=2026-11-30');

        $response->assertOk()->assertJsonCount(1)->assertJsonPath('0.tenant_id', $a->id);
    }

    public function test_soft_deleted_job_is_not_returned(): void
    {
        $this->seedPermissionCatalog();
        $a = $this->firm('A');
        $user = $this->member($a, 'Interná upratovačka');
        $this->jobFor($user, $a, $this->objectIn($a), '2026-11-10')->delete();

        $this->apiAs($user, $a)->getJson('/api/my-jobs?from=2026-11-01&to=2026-11-30')->assertOk()->assertJsonCount(0);
    }

    public function test_jobs_of_a_deactivated_membership_are_not_returned(): void
    {
        $this->seedPermissionCatalog();
        $a = $this->firm('A');
        $b = $this->firm('B');
        $user = $this->member($a, 'Interná upratovačka');
        $this->member($b, 'Interná upratovačka', $user);
        $this->jobFor($user, $a, $this->objectIn($a), '2026-11-10');
        $this->jobFor($user, $b, $this->objectIn($b), '2026-11-10');
        $this->membershipOf($user, $b)->update(['is_active' => false]);

        $this->apiAs($user, $a)->getJson('/api/my-jobs?from=2026-11-01&to=2026-11-30')
            ->assertOk()->assertJsonCount(1)->assertJsonPath('0.tenant_id', $a->id);
    }

    public function test_can_complete_is_false_without_record_own_work(): void
    {
        $this->seedPermissionCatalog();
        $this->travelTo(now()->setDate(2026, 11, 10)->setTime(10, 0));
        $a = $this->firm('A');
        $this->revokeRolePermission($a, 'Vedúca', 'record own work');
        $user = $this->member($a, 'Vedúca');
        $this->jobFor($user, $a, $this->objectIn($a), '2026-11-10');

        $this->apiAs($user, $a)->getJson('/api/my-jobs?from=2026-11-01&to=2026-11-30')
            ->assertOk()->assertJsonPath('0.can_complete', false);
    }

    public function test_unparseable_or_non_day_dates_are_422_not_500(): void
    {
        $this->seedPermissionCatalog();
        $a = $this->firm('A');
        $user = $this->member($a, 'Interná upratovačka');
        $client = $this->apiAs($user, $a);

        $client->getJson('/api/my-jobs?from=garbage&to=2026-11-30')->assertUnprocessable()->assertJsonValidationErrors('from');
        $client->getJson('/api/my-jobs?from=2026-11-01&to=garbage')->assertUnprocessable()->assertJsonValidationErrors('to');
        $client->getJson('/api/my-jobs?from='.urlencode('2026-11-01 10:00').'&to=2026-11-30')->assertUnprocessable()->assertJsonValidationErrors('from');
        $client->getJson('/api/my-jobs?from=2026-11-01&to='.urlencode('2026-11-30 10:00'))->assertUnprocessable()->assertJsonValidationErrors('to');
    }

    public function test_caller_without_the_ability_gets_403_even_with_an_invalid_range(): void
    {
        $this->seedPermissionCatalog();
        $b = $this->firm('B');
        $user = $this->member($b, 'Účtovníčka');

        $this->apiAs($user, $b)->getJson('/api/my-jobs?from=garbage&to=nope')->assertForbidden();
    }

    public function test_requires_authentication(): void
    {
        $this->getJson('/api/my-jobs?from=2026-11-01&to=2026-11-30')->assertUnauthorized();
    }

    public function test_a_record_carries_its_item_names_and_photo_count_and_a_regular_job_has_none(): void
    {
        $this->seedPermissionCatalog();
        $a = $this->firm('A');
        $b = $this->firm('B');
        $user = $this->member($a, 'Interná upratovačka');
        $this->member($b, 'Interná upratovačka', $user);
        $object = $this->objectIn($a, 'Alfa');
        $items = $this->itemsFor($object);
        $regular = $this->jobFor($user, $a, $object, '2026-11-05');
        $objectB = $this->objectIn($b, 'Beta');
        $itemsB = $this->itemsFor($objectB, ['Okna' => '30.00']);
        $this->jobFor($user, $b, $objectB, '2026-11-02');
        $this->travelTo(now()->setDate(2026, 11, 13)->setTime(12, 0));
        $record = $this->apiAs($user, $a)->postJson('/api/work-records', $this->recordPayload($object, array_values($items), ['performed_at' => '2026-11-10T08:00:00Z']))->json('id');
        $recordB = $this->apiAs($user, $b)->postJson('/api/work-records', $this->recordPayload($objectB, array_values($itemsB), ['performed_at' => '2026-11-11T08:00:00Z']))->json('id');
        $this->assertIsString($record);
        $this->assertIsString($recordB);
        Storage::fake('local');
        config(['scheduling.work_records.photos.disk' => 'local']);
        $this->apiAs($user, $a)->post("/api/jobs/{$record}/photos", ['file' => UploadedFile::fake()->image('a.jpg'), 'client_uuid' => (string) Str::uuid()], ['Accept' => 'application/json'])->assertCreated();
        $this->apiAs($user, $a)->post("/api/jobs/{$record}/photos", ['file' => UploadedFile::fake()->image('b.jpg'), 'client_uuid' => (string) Str::uuid()], ['Accept' => 'application/json'])->assertCreated();

        $response = $this->apiAs($user, $a)->getJson('/api/my-jobs?from=2026-11-01&to=2026-11-30')->assertOk();

        /** @var list<array<string, mixed>> $json */
        $json = $response->json();
        $rows = array_column($json, null, 'id');
        $this->assertSame([], $rows[$regular->id]['items']);
        $this->assertSame(0, $rows[$regular->id]['photos_count']);
        $this->assertEqualsCanonicalizing(['Kosenie', 'Zametanie'], $rows[$record]['items']);
        $this->assertSame(2, $rows[$record]['photos_count']);
        $this->assertSame(['Okna'], $rows[$recordB]['items']);
        $this->assertSame(0, $rows[$recordB]['photos_count']);
        $this->assertStringNotContainsString('price', (string) $response->getContent());
    }
}
