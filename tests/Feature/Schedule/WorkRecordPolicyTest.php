<?php

declare(strict_types=1);

namespace Tests\Feature\Schedule;

use App\Models\CleaningObject;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\ScheduledJob;
use App\Models\ScheduledJobItem;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use App\Policies\ObjectPolicy;
use App\Policies\ScheduledJobPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class WorkRecordPolicyTest extends TestCase
{
    use RefreshDatabase;

    private ScheduledJobPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = app(ScheduledJobPolicy::class);
    }

    /** @return array{0: User, 1: Tenant, 2: CleaningObject} */
    private function actor(string $role): array
    {
        $tenant = Tenant::factory()->create();
        $user = $this->actingAsTenantUser($role, $tenant);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $object = CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);

        return [$user, $tenant, $object];
    }

    private function jobFor(Tenant $tenant, CleaningObject $object, ?TenantMembership $membership, bool $withItem): ScheduledJob
    {
        $job = ScheduledJob::factory()->forObject($object)->completed()->create([
            'tenant_id' => $tenant->id,
            'assigned_membership_id' => $membership?->id,
        ]);

        if ($withItem) {
            ScheduledJobItem::factory()->forJob($job)->create();
        }

        return $job->refresh();
    }

    private function membership(User $user, Tenant $tenant): TenantMembership
    {
        return TenantMembership::query()->where('user_id', $user->id)->where('tenant_id', $tenant->id)->firstOrFail();
    }

    public function test_record_anywhere_follows_the_record_own_work_permission(): void
    {
        [$cleaner] = $this->actor('Interná upratovačka');
        $this->assertTrue($this->policy->recordAnywhere($cleaner));

        [$accountant] = $this->actor('Účtovníčka');
        $this->assertFalse($this->policy->recordAnywhere($accountant));
    }

    public function test_record_work_needs_permission_reach_and_an_active_object(): void
    {
        [$cleaner, $tenant, $object] = $this->actor('Interná upratovačka');
        $this->assertFalse($this->policy->recordWork($cleaner, $object));

        $this->jobFor($tenant, $object, $this->membership($cleaner, $tenant), false);
        $this->assertTrue($this->policy->recordWork($cleaner, $object));

        $object->update(['is_active' => false]);
        $this->assertFalse($this->policy->recordWork($cleaner, $object->refresh()));

        [$admin, $adminTenant, $adminObject] = $this->actor('Admin');
        $this->jobFor($adminTenant, $adminObject, $this->membership($admin, $adminTenant), false);
        $this->assertTrue($this->policy->recordWork($admin, $adminObject));

        [$accountant, $accTenant, $accObject] = $this->actor('Účtovníčka');
        $this->jobFor($accTenant, $accObject, $this->membership($accountant, $accTenant), false);
        $this->assertFalse($this->policy->recordWork($accountant, $accObject));
    }

    public function test_create_work_record_needs_create_schedule_and_assign_cleaners(): void
    {
        foreach (['Admin' => true, 'Vedúca' => true, 'Sekretárka' => false, 'Účtovníčka' => false, 'Interná upratovačka' => false] as $role => $expected) {
            [$user, , $object] = $this->actor($role);

            $this->assertSame($expected, $this->policy->createWorkRecord($user, $object), $role);
        }
    }

    public function test_update_and_delete_need_edit_schedule_a_work_record_and_no_invoice(): void
    {
        [$admin, $tenant, $object] = $this->actor('Admin');
        $record = $this->jobFor($tenant, $object, null, true);
        $regular = $this->jobFor($tenant, $object, null, false);

        $this->assertTrue($this->policy->updateWorkRecord($admin, $record));
        $this->assertTrue($this->policy->deleteWorkRecord($admin, $record));
        $this->assertFalse($this->policy->updateWorkRecord($admin, $regular));
        $this->assertFalse($this->policy->deleteWorkRecord($admin, $regular));

        $record->forceFill(['invoice_id' => Invoice::factory()->create(['tenant_id' => $tenant->id])->id])->save();
        $this->assertFalse($this->policy->updateWorkRecord($admin, $record->refresh()));
        $this->assertFalse($this->policy->deleteWorkRecord($admin, $record));

        [$secretary, $secTenant, $secObject] = $this->actor('Sekretárka');
        $this->assertFalse($this->policy->updateWorkRecord($secretary, $this->jobFor($secTenant, $secObject, null, true)));

        [$cleaner, $cTenant, $cObject] = $this->actor('Interná upratovačka');
        $own = $this->jobFor($cTenant, $cObject, $this->membership($cleaner, $cTenant), true);
        $this->assertFalse($this->policy->updateWorkRecord($cleaner, $own));
        $this->assertFalse($this->policy->deleteWorkRecord($cleaner, $own));
    }

    public function test_add_photo_is_for_the_assignee_of_a_fresh_work_record(): void
    {
        [$cleaner, $tenant, $object] = $this->actor('Interná upratovačka');
        $mine = $this->jobFor($tenant, $object, $this->membership($cleaner, $tenant), true);
        $regular = $this->jobFor($tenant, $object, $this->membership($cleaner, $tenant), false);
        $others = $this->jobFor($tenant, $object, null, true);

        $this->assertTrue($this->policy->addPhoto($cleaner, $mine));
        $this->assertFalse($this->policy->addPhoto($cleaner, $regular));
        $this->assertFalse($this->policy->addPhoto($cleaner, $others));

        $this->travel(7 * 24 * 60 - 5)->minutes();
        $this->assertTrue($this->policy->addPhoto($cleaner, $mine));
        $this->travel(10)->minutes();
        $this->assertFalse($this->policy->addPhoto($cleaner, $mine));

        [$admin, $aTenant, $aObject] = $this->actor('Admin');
        $this->assertFalse($this->policy->addPhoto($admin, $this->jobFor($aTenant, $aObject, null, true)));
    }

    public function test_object_policy_visit_abilities_follow_view_all_objects_and_edit_objects(): void
    {
        $objects = new ObjectPolicy;

        foreach (['Admin' => [true, true, true], 'Sekretárka' => [true, true, true], 'Vedúca' => [true, true, false], 'Interná upratovačka' => [false, false, false]] as $role => [$items, $records, $manage]) {
            [$user, , $object] = $this->actor($role);

            $this->assertSame($items, $objects->viewVisitItems($user), $role);
            $this->assertSame($records, $objects->viewVisitRecords($user), $role);
            $this->assertSame($manage, $objects->manageVisitItems($user, $object), $role);
        }
    }

    public function test_vedouca_holds_the_permission_but_the_reach_and_own_record_still_bound_her(): void
    {
        [$lead, $tenant, $object] = $this->actor('Vedúca');
        $this->assertTrue($this->policy->recordAnywhere($lead));
        $this->assertFalse($this->policy->recordWork($lead, $object));

        $others = $this->jobFor($tenant, $object, null, true);
        $this->assertFalse($this->policy->addPhoto($lead, $others));

        $this->jobFor($tenant, $object, $this->membership($lead, $tenant), false);
        $this->assertTrue($this->policy->recordWork($lead, $object));
    }

    public function test_view_work_record_photo_follows_view_or_the_overview_on_the_object(): void
    {
        foreach (['Admin' => true, 'Sekretárka' => true, 'Účtovníčka' => true, 'Interná upratovačka' => false] as $role => $expected) {
            [$user, $tenant, $object] = $this->actor($role);
            $record = $this->jobFor($tenant, $object, null, true);

            $this->assertSame($expected, $this->policy->viewWorkRecordPhoto($user, $record), $role);
        }

        [$accountant, $tenant, $object] = $this->actor('Účtovníčka');
        $this->assertFalse($this->policy->viewWorkRecordPhoto($accountant, $this->jobFor($tenant, $object, null, false)));
    }
}
