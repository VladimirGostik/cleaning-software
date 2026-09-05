<?php

declare(strict_types=1);

namespace Tests\Feature\Objects;

use App\Data\Schedule\JobListItemData;
use App\Models\CleaningObject;
use App\Models\Client;
use App\Models\ScheduledJob;
use App\Services\ObjectService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ObjectDeactivationRelationTest extends TestCase
{
    use RefreshDatabase;

    public function test_scheduled_job_still_resolves_cleaning_object_after_deactivation(): void
    {
        // Arrange
        $user = $this->actingAsTenantUser('Admin');
        $tenant = $user->ownedTenants()->first();

        $client = Client::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Klient A']);
        $object = CleaningObject::factory()->create([
            'tenant_id' => $tenant->id,
            'client_id' => $client->id,
            'name' => 'Objekt A',
        ]);
        $job = ScheduledJob::factory()->create([
            'tenant_id' => $tenant->id,
            'cleaning_object_id' => $object->id,
        ]);

        // Act
        app(ObjectService::class)->deactivate($object);

        // Assert
        $freshJob = $job->fresh();
        $this->assertNotNull($freshJob);
        $this->assertNotNull($freshJob->cleaningObject);
        $this->assertSame('Objekt A', $freshJob->cleaningObject->name);

        $listItem = JobListItemData::fromModel($freshJob->load('cleaningObject.client'));
        $this->assertSame('Objekt A', $listItem->object_name);
        $this->assertSame('Klient A', $listItem->client_name);
    }

    public function test_scheduled_job_loses_cleaning_object_when_object_is_soft_deleted(): void
    {
        // Arrange
        $user = $this->actingAsTenantUser('Admin');
        $tenant = $user->ownedTenants()->first();

        $client = Client::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Klient A']);
        $object = CleaningObject::factory()->create([
            'tenant_id' => $tenant->id,
            'client_id' => $client->id,
            'name' => 'Objekt A',
        ]);
        $job = ScheduledJob::factory()->create([
            'tenant_id' => $tenant->id,
            'cleaning_object_id' => $object->id,
        ]);

        // Act — raw soft delete (the bug this change avoids in the deactivate flow)
        $object->delete();

        // Assert
        $freshJob = $job->fresh();
        $this->assertNotNull($freshJob);
        $this->assertNull($freshJob->cleaningObject);

        $listItem = JobListItemData::fromModel($freshJob->load('cleaningObject.client'));
        $this->assertSame('', $listItem->object_name);
    }
}
