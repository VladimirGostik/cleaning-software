<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Enums\JobStatusEnum;
use App\Enums\JobTypeEnum;
use App\Models\CleaningObject;
use App\Models\ObjectVisitItem;
use App\Models\ScheduledJob;
use App\Models\ScheduledJobItem;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\Support\CreatesWorkRecordFixtures;
use Tests\TestCase;

final class WorkRecordApiTest extends TestCase
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
     * @param  list<ObjectVisitItem>  $items
     * @param  array<string, mixed>  $overrides
     * @return TestResponse<JsonResponse>
     */
    private function submit(User $user, Tenant $firm, CleaningObject $object, array $items, array $overrides): TestResponse
    {
        return $this->apiAs($user, $firm)->postJson('/api/work-records', $this->recordPayload($object, $items, $overrides));
    }

    public function test_record_creates_a_completed_one_off_job_with_snapshot_rows_and_no_price_in_the_response(): void
    {
        $firm = $this->firm();
        $user = $this->member($firm, 'Interná upratovačka');
        $object = $this->objectIn($firm);
        $items = $this->itemsFor($object);
        $this->assignedJob($user, $firm, $object);
        $at = now()->subHours(2);

        $response = $this->apiAs($user, $firm)->postJson('/api/work-records', $this->recordPayload($object, array_values($items), [
            'performed_at' => $at->toIso8601ZuluString(),
            'note' => 'Hotovo skoro',
        ]));

        $response->assertCreated()
            ->assertJsonPath('status', 'completed')
            ->assertJsonPath('type', 'one_off')
            ->assertJsonPath('note', 'Hotovo skoro')
            ->assertJsonPath('can_complete', false)
            ->assertJsonPath('photos_count', 0);
        $this->assertEqualsCanonicalizing(['Kosenie', 'Zametanie'], $response->json('items'));
        $this->assertStringNotContainsString('price', (string) $response->getContent());

        $job = ScheduledJob::query()->whereKey($response->json('id'))->firstOrFail();
        $this->assertSame($this->membershipOf($user, $firm)->id, $job->assigned_membership_id);
        $this->assertSame(JobTypeEnum::OneOff, $job->type);
        $this->assertSame(JobStatusEnum::Completed, $job->status);
        $this->assertSame($at->toIso8601ZuluString(), $job->completed_at?->toIso8601ZuluString());
        $this->assertNull($job->work_breakdown_id);
        $this->assertNull($job->contract_id);
        $this->assertNull($job->started_at);
        $this->assertSame($firm->id, $job->tenant_id);
        $this->assertSame(2, $job->items()->count());
        $this->assertEqualsCanonicalizing(['10.00', '5.50'], $job->items->pluck('unit_price')->all());
    }

    public function test_snapshot_survives_a_price_change_and_a_delete_of_the_object_item(): void
    {
        $firm = $this->firm();
        $user = $this->member($firm, 'Interná upratovačka');
        $object = $this->objectIn($firm);
        $items = $this->itemsFor($object);
        $this->assignedJob($user, $firm, $object);
        $id = $this->apiAs($user, $firm)->postJson('/api/work-records', $this->recordPayload($object, array_values($items)))->json('id');

        $items['Kosenie']->update(['price' => '99.00', 'name' => 'Kosenie nove']);
        $items['Zametanie']->delete();

        $rows = ScheduledJobItem::query()->where('cleaning_job_id', $id)->orderBy('name')->get();
        $this->assertSame(['Kosenie', 'Zametanie'], $rows->pluck('name')->all());
        $this->assertSame(['10.00', '5.50'], $rows->pluck('unit_price')->all());

        $items['Zametanie']->forceDelete();
        $this->assertNull(ScheduledJobItem::query()->where('cleaning_job_id', $id)->where('name', 'Zametanie')->firstOrFail()->object_visit_item_id);
    }

    public function test_same_client_uuid_twice_creates_one_job_and_answers_200(): void
    {
        $firm = $this->firm();
        $user = $this->member($firm, 'Interná upratovačka');
        $object = $this->objectIn($firm);
        $items = array_values($this->itemsFor($object));
        $this->assignedJob($user, $firm, $object);
        $payload = $this->recordPayload($object, $items);

        $first = $this->apiAs($user, $firm)->postJson('/api/work-records', $payload)->assertCreated();
        $second = $this->apiAs($user, $firm)->postJson('/api/work-records', $payload)->assertOk();

        $this->assertSame($first->json('id'), $second->json('id'));
        $this->assertSame(1, ScheduledJob::query()->where('client_uuid', $payload['client_uuid'])->count());
        $this->assertSame(2, ScheduledJobItem::query()->count());
    }

    public function test_a_retry_after_the_admin_deleted_the_record_does_not_recreate_it(): void
    {
        $firm = $this->firm();
        $user = $this->member($firm, 'Interná upratovačka');
        $object = $this->objectIn($firm);
        $items = array_values($this->itemsFor($object));
        $this->assignedJob($user, $firm, $object);
        $payload = $this->recordPayload($object, $items);

        $id = $this->apiAs($user, $firm)->postJson('/api/work-records', $payload)->json('id');
        ScheduledJob::query()->whereKey($id)->firstOrFail()->delete();

        $this->apiAs($user, $firm)->postJson('/api/work-records', $payload)->assertOk()->assertJsonPath('id', $id);
        $this->assertSame(1, ScheduledJob::withTrashed()->where('client_uuid', $payload['client_uuid'])->count());
        $this->assertSame(0, ScheduledJob::query()->where('client_uuid', $payload['client_uuid'])->count());
    }

    public function test_a_client_uuid_of_another_person_is_refused(): void
    {
        $firm = $this->firm();
        $user = $this->member($firm, 'Interná upratovačka');
        $other = $this->member($firm, 'Interná upratovačka');
        $object = $this->objectIn($firm);
        $items = array_values($this->itemsFor($object));
        $this->assignedJob($user, $firm, $object);
        $this->assignedJob($other, $firm, $object);
        $payload = $this->recordPayload($object, $items);

        $this->apiAs($user, $firm)->postJson('/api/work-records', $payload)->assertCreated();
        $this->apiAs($other, $firm)->postJson('/api/work-records', $payload)->assertUnprocessable()->assertJsonValidationErrors('client_uuid');
    }

    public function test_object_without_an_assigned_job_is_forbidden(): void
    {
        $firm = $this->firm();
        $user = $this->member($firm, 'Interná upratovačka');
        $object = $this->objectIn($firm);
        $items = array_values($this->itemsFor($object));

        $this->apiAs($user, $firm)->postJson('/api/work-records', $this->recordPayload($object, $items))->assertForbidden();
        $this->assertSame(0, ScheduledJob::query()->count());
    }

    public function test_a_cancelled_job_or_an_earlier_record_of_hers_grants_the_reach(): void
    {
        $firm = $this->firm();
        $user = $this->member($firm, 'Interná upratovačka');
        $cancelled = $this->objectIn($firm, 'Cancelled');
        $earlier = $this->objectIn($firm, 'Earlier');
        $this->assignedJob($user, $firm, $cancelled, JobStatusEnum::Cancelled);
        $this->assignedJob($user, $firm, $earlier, JobStatusEnum::Completed, '2026-01-05');

        foreach ([$cancelled, $earlier] as $object) {
            $items = array_values($this->itemsFor($object));
            $this->apiAs($user, $firm)->postJson('/api/work-records', $this->recordPayload($object, $items))->assertCreated();
        }
    }

    public function test_a_soft_deleted_job_no_longer_grants_the_reach(): void
    {
        $firm = $this->firm();
        $user = $this->member($firm, 'Interná upratovačka');
        $object = $this->objectIn($firm);
        $items = array_values($this->itemsFor($object));
        $this->assignedJob($user, $firm, $object)->delete();

        $this->apiAs($user, $firm)->postJson('/api/work-records', $this->recordPayload($object, $items))->assertForbidden();
    }

    public function test_permission_is_checked_before_the_body(): void
    {
        $firm = $this->firm();
        $user = $this->member($firm, 'Účtovníčka');

        $this->apiAs($user, $firm)->postJson('/api/work-records', ['item_ids' => 'nonsense'])->assertForbidden();
    }

    public function test_validation_rules(): void
    {
        $firm = $this->firm();
        $user = $this->member($firm, 'Interná upratovačka');
        $object = $this->objectIn($firm);
        $foreignObject = $this->objectIn($firm, 'Foreign');
        $items = array_values($this->itemsFor($object));
        $foreignItems = array_values($this->itemsFor($foreignObject));
        $inactive = $this->objectIn($firm, 'Inactive', active: false);
        $inactiveItems = array_values($this->itemsFor($inactive));
        $this->assignedJob($user, $firm, $object);
        $this->assignedJob($user, $firm, $inactive);

        $this->submit($user, $firm, $object, $items, ['item_ids' => []])->assertUnprocessable()->assertJsonValidationErrors('item_ids');
        $this->submit($user, $firm, $object, $items, ['item_ids' => [$items[0]->id, $items[0]->id]])->assertUnprocessable()->assertJsonValidationErrors('item_ids.1');
        $this->submit($user, $firm, $object, $items, ['item_ids' => [$foreignItems[0]->id]])->assertUnprocessable()->assertJsonValidationErrors('item_ids');
        $this->submit($user, $firm, $object, $items, ['item_ids' => [(string) Str::uuid()]])->assertUnprocessable()->assertJsonValidationErrors('item_ids');
        $this->submit($user, $firm, $object, $items, ['note' => str_repeat('a', 2001)])->assertUnprocessable()->assertJsonValidationErrors('note');
        $this->submit($user, $firm, $object, $items, ['cleaning_object_id' => (string) Str::uuid()])->assertUnprocessable()->assertJsonValidationErrors('cleaning_object_id');
        $this->submit($user, $firm, $object, $items, ['client_uuid' => 'nope'])->assertUnprocessable()->assertJsonValidationErrors('client_uuid');
        $this->apiAs($user, $firm)->postJson('/api/work-records', $this->recordPayload($inactive, $inactiveItems))
            ->assertUnprocessable()->assertJsonValidationErrors('cleaning_object_id');
        $this->assertSame(0, ScheduledJob::query()->where('type', JobTypeEnum::OneOff)->whereHas('items')->count());
    }

    public function test_time_window(): void
    {
        $firm = $this->firm();
        $user = $this->member($firm, 'Interná upratovačka');
        $object = $this->objectIn($firm);
        $items = array_values($this->itemsFor($object));
        $this->assignedJob($user, $firm, $object);
        $at = fn (string $value) => $this->apiAs($user, $firm)->postJson('/api/work-records', $this->recordPayload($object, $items, ['performed_at' => $value]));

        $at(now()->subDays(8)->toIso8601ZuluString())->assertUnprocessable()->assertJsonValidationErrors('performed_at');
        $at(now()->addMinutes(10)->toIso8601ZuluString())->assertUnprocessable()->assertJsonValidationErrors('performed_at');
        $at(now()->subDays(7)->toIso8601ZuluString())->assertCreated();
        $at(now()->addMinutes(4)->toIso8601ZuluString())->assertCreated();
    }

    public function test_malformed_performed_at_is_a_422_never_a_500(): void
    {
        $firm = $this->firm();
        $user = $this->member($firm, 'Interná upratovačka');
        $object = $this->objectIn($firm);
        $items = array_values($this->itemsFor($object));
        $this->assignedJob($user, $firm, $object);

        foreach (['yesterday', '2026-13-45T99:99:99Z', '2026-11-13', '', ['x'], 12345, '2026-11-13T10:00:00'] as $value) {
            $this->apiAs($user, $firm)->postJson('/api/work-records', $this->recordPayload($object, $items, ['performed_at' => $value]))
                ->assertUnprocessable()->assertJsonValidationErrors('performed_at');
        }

        $this->apiAs($user, $firm)->postJson('/api/work-records', $this->recordPayload($object, $items, ['performed_at' => '2026-11-13T09:30:00.123+01:00']))->assertCreated();
    }

    public function test_the_local_day_decides_date_and_start_time(): void
    {
        $firm = $this->firm();
        $user = $this->member($firm, 'Interná upratovačka');
        $object = $this->objectIn($firm);
        $items = array_values($this->itemsFor($object));
        $this->assignedJob($user, $firm, $object);
        $this->travelTo(now()->setDate(2026, 11, 2)->setTime(12, 0));

        $late = $this->apiAs($user, $firm)->postJson('/api/work-records', $this->recordPayload($object, $items, ['performed_at' => '2026-10-31T23:30:00Z']))->assertCreated();
        $local = $this->apiAs($user, $firm)->postJson('/api/work-records', $this->recordPayload($object, $items, ['performed_at' => '2026-11-01T22:30:00Z']))->assertCreated();

        $late->assertJsonPath('scheduled_date', '2026-11-01')->assertJsonPath('start_time', '00:30:00');
        $local->assertJsonPath('scheduled_date', '2026-11-01')->assertJsonPath('start_time', '23:30:00');
    }

    public function test_two_records_with_the_same_items_on_the_same_day_are_both_kept(): void
    {
        $firm = $this->firm();
        $user = $this->member($firm, 'Interná upratovačka');
        $object = $this->objectIn($firm);
        $items = array_values($this->itemsFor($object));
        $this->assignedJob($user, $firm, $object);

        $this->apiAs($user, $firm)->postJson('/api/work-records', $this->recordPayload($object, $items))->assertCreated();
        $this->apiAs($user, $firm)->postJson('/api/work-records', $this->recordPayload($object, $items))->assertCreated();

        $this->assertSame(2, ScheduledJob::query()->whereHas('items')->count());
    }

    public function test_a_person_recording_for_two_firms_sends_each_record_to_its_own_firm(): void
    {
        $a = $this->firm('A');
        $b = $this->firm('B');
        $user = $this->member($a, 'Interná upratovačka');
        $this->member($b, 'Interná upratovačka', $user);
        $objectA = $this->objectIn($a);
        $objectB = $this->objectIn($b);
        $itemsA = array_values($this->itemsFor($objectA));
        $itemsB = array_values($this->itemsFor($objectB));
        $this->assignedJob($user, $a, $objectA);
        $this->assignedJob($user, $b, $objectB);

        $this->apiAs($user, $a)->postJson('/api/work-records', $this->recordPayload($objectA, $itemsA))->assertCreated();
        $this->apiAs($user, $b)->postJson('/api/work-records', $this->recordPayload($objectB, $itemsB))->assertCreated();
        $this->apiAs($user, $a)->postJson('/api/work-records', $this->recordPayload($objectB, $itemsB))->assertUnprocessable()->assertJsonValidationErrors('cleaning_object_id');

        $records = ScheduledJob::withoutGlobalScopes()->whereHas('items', fn ($q) => $q->withoutGlobalScopes())->get();
        $this->assertEqualsCanonicalizing([$a->id, $b->id], $records->pluck('tenant_id')->all());
        $this->assertSame([$a->id], ScheduledJobItem::withoutGlobalScopes()->whereIn('object_visit_item_id', array_map(fn (ObjectVisitItem $i): string => $i->id, $itemsA))->pluck('tenant_id')->unique()->values()->all());
    }

    public function test_unauthenticated_is_rejected(): void
    {
        $this->postJson('/api/work-records', [])->assertUnauthorized();
    }

    public function test_a_replay_is_answered_after_the_window_closed_or_the_object_was_deactivated(): void
    {
        $firm = $this->firm();
        $user = $this->member($firm, 'Interná upratovačka');
        $object = $this->objectIn($firm);
        $items = array_values($this->itemsFor($object));
        $this->assignedJob($user, $firm, $object);
        $payload = $this->recordPayload($object, $items, ['performed_at' => now()->subDays(6)->toIso8601ZuluString()]);
        $id = $this->apiAs($user, $firm)->postJson('/api/work-records', $payload)->assertCreated()->json('id');

        $this->travel(3)->days();
        $this->apiAs($user, $firm)->postJson('/api/work-records', $payload)->assertOk()->assertJsonPath('id', $id);

        $object->update(['is_active' => false]);
        $this->apiAs($user, $firm)->postJson('/api/work-records', $payload)->assertOk()->assertJsonPath('id', $id);
        $this->assertSame(1, ScheduledJob::query()->where('client_uuid', $payload['client_uuid'])->count());
    }

    public function test_a_replay_with_a_different_body_returns_the_stored_record_unchanged(): void
    {
        $firm = $this->firm();
        $user = $this->member($firm, 'Interná upratovačka');
        $object = $this->objectIn($firm);
        $items = $this->itemsFor($object);
        $this->assignedJob($user, $firm, $object);
        $payload = $this->recordPayload($object, [$items['Kosenie']], ['note' => 'Original']);
        $id = $this->apiAs($user, $firm)->postJson('/api/work-records', $payload)->assertCreated()->json('id');

        $response = $this->apiAs($user, $firm)->postJson('/api/work-records', [
            ...$payload,
            'item_ids' => [$items['Zametanie']->id],
            'note' => 'Changed',
            'performed_at' => now()->subDays(2)->toIso8601ZuluString(),
        ])->assertOk();

        $response->assertJsonPath('id', $id)->assertJsonPath('note', 'Original');
        $this->assertSame(['Kosenie'], $response->json('items'));
        $this->assertSame(1, ScheduledJob::query()->where('client_uuid', $payload['client_uuid'])->count());
    }

    public function test_upper_case_ids_are_accepted_and_do_not_break_the_snapshot(): void
    {
        $firm = $this->firm();
        $user = $this->member($firm, 'Interná upratovačka');
        $object = $this->objectIn($firm);
        $items = array_values($this->itemsFor($object));
        $this->assignedJob($user, $firm, $object);
        $payload = $this->recordPayload($object, $items, [
            'item_ids' => array_map(static fn (ObjectVisitItem $item): string => strtoupper($item->id), $items),
            'client_uuid' => strtoupper((string) Str::uuid()),
        ]);

        $first = $this->apiAs($user, $firm)->postJson('/api/work-records', $payload)->assertCreated();
        $second = $this->apiAs($user, $firm)->postJson('/api/work-records', $payload)->assertOk();

        $this->assertSame($first->json('id'), $second->json('id'));
        $this->assertSame(2, ScheduledJobItem::query()->count());
    }
}
