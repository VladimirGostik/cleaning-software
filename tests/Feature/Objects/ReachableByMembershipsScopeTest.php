<?php

declare(strict_types=1);

namespace Tests\Feature\Objects;

use App\Models\CleaningObject;
use App\Models\Client;
use App\Models\ScheduledJob;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Scopes\TenantScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesTaskFixtures;
use Tests\TestCase;

final class ReachableByMembershipsScopeTest extends TestCase
{
    use CreatesTaskFixtures, RefreshDatabase;

    private function objectWithJobFor(Tenant $tenant, ?TenantMembership $membership, bool $deletedJob = false): CleaningObject
    {
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $object = CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);
        $job = ScheduledJob::factory()->forObject($object);
        $job = $membership !== null ? $job->assignedTo($membership) : $job;
        $created = $job->create(['tenant_id' => $tenant->id]);

        if ($deletedJob) {
            $created->delete();
        }

        return $object;
    }

    public function test_returns_exactly_the_objects_with_a_job_of_those_memberships_across_firms(): void
    {
        $this->seedPermissionCatalog();
        $a = $this->firm('A');
        $b = $this->firm('B');
        $user = $this->member($a, 'Interná upratovačka');
        $this->member($b, 'Interná upratovačka', $user);
        $other = $this->member($a, 'Interná upratovačka');
        $mine = [$this->membershipOf($user, $a), $this->membershipOf($user, $b)];

        $inA = $this->objectWithJobFor($a, $mine[0]);
        $inB = $this->objectWithJobFor($b, $mine[1]);
        $this->objectWithJobFor($a, $this->membershipOf($other, $a));
        $this->objectWithJobFor($a, null);
        $this->objectWithJobFor($a, $mine[0], deletedJob: true);

        $ids = CleaningObject::withoutGlobalScope(TenantScope::class)
            ->reachableByMemberships([$mine[0]->id, $mine[1]->id])
            ->pluck('id')
            ->all();

        $this->assertEqualsCanonicalizing([$inA->id, $inB->id], $ids);
    }

    public function test_empty_membership_list_returns_nothing(): void
    {
        $this->seedPermissionCatalog();
        $a = $this->firm('A');
        $user = $this->member($a, 'Interná upratovačka');
        $this->objectWithJobFor($a, $this->membershipOf($user, $a));

        $this->assertSame([], CleaningObject::withoutGlobalScope(TenantScope::class)->reachableByMemberships([])->pluck('id')->all());
    }
}
