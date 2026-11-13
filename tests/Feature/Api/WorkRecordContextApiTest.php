<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Enums\JobStatusEnum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesWorkRecordFixtures;
use Tests\TestCase;

final class WorkRecordContextApiTest extends TestCase
{
    use CreatesWorkRecordFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedPermissionCatalog();
    }

    public function test_lists_reachable_objects_with_live_items_across_firms_without_prices(): void
    {
        $a = $this->firm('A');
        $b = $this->firm('B');
        $user = $this->member($a, 'Interná upratovačka');
        $this->member($b, 'Interná upratovačka', $user);
        $objectA = $this->objectIn($a, 'Alfa');
        $objectB = $this->objectIn($b, 'Beta');
        $itemsA = $this->itemsFor($objectA);
        $this->itemsFor($objectB, ['Okna' => '30.00']);
        $itemsA['Zametanie']->delete();
        $this->assignedJob($user, $a, $objectA, JobStatusEnum::Cancelled);
        $this->assignedJob($user, $b, $objectB);

        $response = $this->apiAs($user, $a)->getJson('/api/work-records/context');

        $response->assertOk()
            ->assertJsonPath('backdate_days', 7)
            ->assertJsonPath('max_photos', 5)
            ->assertJsonPath('max_photo_size_kb', 10240)
            ->assertJsonCount(2, 'objects')
            ->assertJsonPath('objects.0.name', 'Alfa')
            ->assertJsonPath('objects.0.tenant_id', $a->id)
            ->assertJsonPath('objects.0.object_id', $objectA->id)
            ->assertJsonPath('objects.0.street', 'Hlavna 1')
            ->assertJsonCount(1, 'objects.0.items')
            ->assertJsonPath('objects.0.items.0.name', 'Kosenie')
            ->assertJsonPath('objects.1.name', 'Beta')
            ->assertJsonPath('objects.1.tenant_id', $b->id);
        $this->assertStringNotContainsString('price', (string) $response->getContent());
    }

    public function test_leaves_out_unreachable_inactive_item_less_and_foreign_person_objects(): void
    {
        $firm = $this->firm();
        $user = $this->member($firm, 'Interná upratovačka');
        $other = $this->member($firm, 'Interná upratovačka');
        $unreachable = $this->objectIn($firm, 'Unreachable');
        $inactive = $this->objectIn($firm, 'Inactive', active: false);
        $noItems = $this->objectIn($firm, 'NoItems');
        $othersOnly = $this->objectIn($firm, 'OthersOnly');
        $deletedJob = $this->objectIn($firm, 'DeletedJob');
        $ok = $this->objectIn($firm, 'Ok');
        foreach ([$unreachable, $inactive, $othersOnly, $deletedJob, $ok] as $object) {
            $this->itemsFor($object);
        }
        $this->assignedJob($user, $firm, $inactive);
        $this->assignedJob($user, $firm, $noItems);
        $this->assignedJob($other, $firm, $othersOnly);
        $this->assignedJob($user, $firm, $deletedJob)->delete();
        $this->assignedJob($user, $firm, $ok);

        $this->apiAs($user, $firm)->getJson('/api/work-records/context')
            ->assertOk()
            ->assertJsonCount(1, 'objects')
            ->assertJsonPath('objects.0.name', 'Ok');
    }

    public function test_leaves_out_a_firm_where_the_role_cannot_record(): void
    {
        $a = $this->firm('A');
        $b = $this->firm('B');
        $this->revokeRolePermission($b, 'Vedúca', 'record own work');
        $user = $this->member($a, 'Interná upratovačka');
        $this->member($b, 'Vedúca', $user);
        $objectA = $this->objectIn($a);
        $objectB = $this->objectIn($b);
        $this->itemsFor($objectA);
        $this->itemsFor($objectB);
        $this->assignedJob($user, $a, $objectA);
        $this->assignedJob($user, $b, $objectB);

        $this->apiAs($user, $a)->getJson('/api/work-records/context')
            ->assertOk()
            ->assertJsonCount(1, 'objects')
            ->assertJsonPath('objects.0.tenant_id', $a->id);
    }

    public function test_forbidden_when_no_firm_grants_record_own_work(): void
    {
        $firm = $this->firm();
        $user = $this->member($firm, 'Účtovníčka');

        $this->apiAs($user, $firm)->getJson('/api/work-records/context')->assertForbidden();
    }
}
