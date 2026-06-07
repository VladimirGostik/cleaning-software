<?php

declare(strict_types=1);

namespace Tests\Feature\Objects;

use App\Models\CleaningObject;
use App\Models\Client;
use App\Models\Tenant;
use App\Services\ClientService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ClientServiceObjectCascadeTest extends TestCase
{
    use RefreshDatabase;

    public function test_delete_client_soft_deletes_its_objects(): void
    {
        // Arrange
        $tenant = Tenant::factory()->create();
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $object = CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);

        // Act
        app(ClientService::class)->delete($client);

        // Assert — object row has deleted_at set
        $this->assertSoftDeleted('objects', ['id' => $object->id]);
        $this->assertDatabaseHas('objects', ['id' => $object->id]);
    }

    public function test_delete_client_soft_deletes_all_objects_when_multiple(): void
    {
        // Arrange
        $tenant = Tenant::factory()->create();
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);

        CleaningObject::factory()->count(3)->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);

        // Act
        app(ClientService::class)->delete($client);

        // Assert — all 3 objects soft-deleted
        $notDeleted = CleaningObject::withoutTrashed()
            ->where('client_id', $client->id)
            ->count();

        $this->assertSame(0, $notDeleted, 'All objects must be soft-deleted when client is deleted');

        $softDeleted = CleaningObject::onlyTrashed()
            ->where('client_id', $client->id)
            ->count();

        $this->assertSame(3, $softDeleted, 'Exactly 3 objects must exist as soft-deleted rows');
    }

    public function test_delete_client_without_objects_does_not_throw(): void
    {
        // Arrange
        $tenant = Tenant::factory()->create();
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);

        // Act & Assert — no exception
        app(ClientService::class)->delete($client);
        $this->assertSoftDeleted('clients', ['id' => $client->id]);
    }

    public function test_delete_client_soft_deletes_both_contacts_and_objects_in_transaction(): void
    {
        // Arrange
        $tenant = Tenant::factory()->create();
        $client = Client::factory()->withContacts(2)->create(['tenant_id' => $tenant->id]);
        CleaningObject::factory()->count(2)->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);

        // Act
        app(ClientService::class)->delete($client);

        // Assert contacts soft-deleted
        $this->assertSame(
            0,
            $client->contacts()->count(),
            'Contacts must be soft-deleted',
        );

        // Assert objects soft-deleted
        $this->assertSame(
            0,
            CleaningObject::withoutTrashed()->where('client_id', $client->id)->count(),
            'Objects must be soft-deleted',
        );

        // Both still present in DB (soft delete)
        $this->assertSame(
            2,
            CleaningObject::onlyTrashed()->where('client_id', $client->id)->count(),
        );
    }

    public function test_http_destroy_client_soft_deletes_its_objects(): void
    {
        // Arrange
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Vlastník', $tenant);

        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $object = CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);

        // Act
        $this->delete(route('clients.destroy', $client));

        // Assert
        $this->assertSoftDeleted('objects', ['id' => $object->id]);
        $this->assertDatabaseHas('objects', ['id' => $object->id]);
    }
}
