<?php

declare(strict_types=1);

namespace Tests\Feature\Contacts;

use App\Models\CleaningObject;
use App\Models\Client;
use App\Models\Contact;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

final class ContactsMigrationTest extends TestCase
{
    use RefreshDatabase;

    private const string PATH = 'database/migrations/2026_11_11_000100_create_contacts_table.php';

    private function rollbackMigration(): void
    {
        Artisan::call('migrate:rollback', ['--path' => self::PATH, '--force' => true]);
    }

    private function runMigration(): void
    {
        Artisan::call('migrate', ['--path' => self::PATH, '--force' => true]);
    }

    /**
     * @return array<string, mixed>
     */
    private function legacyRow(string $id, Tenant $tenant, string $parentColumn, string $parentId, string $name, ?string $deletedAt = null): array
    {
        return [
            'id' => $id,
            'tenant_id' => $tenant->id,
            $parentColumn => $parentId,
            'name' => $name,
            'position' => 'Manager',
            'email' => 'a@example.com',
            'phone' => '+421900000000',
            'is_primary' => true,
            'created_at' => '2026-10-01 10:00:00',
            'updated_at' => '2026-10-02 10:00:00',
            'deleted_at' => $deletedAt,
        ];
    }

    private function logActivity(string $subjectType, string $subjectId): void
    {
        DB::table('activity_log')->insert([
            'description' => 'created',
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'event' => 'created',
        ]);
    }

    public function test_up_moves_rows_with_ids_and_deleted_at_and_rewrites_activity_subject_type(): void
    {
        $tenant = Tenant::factory()->create();
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $object = CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);
        $this->rollbackMigration();

        $clientId = (string) Str::uuid7();
        $objectId = (string) Str::uuid7();
        $clientRow = $this->legacyRow($clientId, $tenant, 'client_id', $client->id, 'Client person');
        $objectRow = $this->legacyRow($objectId, $tenant, 'cleaning_object_id', $object->id, 'Object person', '2026-10-05 10:00:00');
        DB::table('client_contacts')->insert($clientRow);
        DB::table('object_contacts')->insert($objectRow);
        $this->logActivity('App\Models\ClientContact', $clientId);
        $this->logActivity('App\Models\ObjectContact', $objectId);

        $this->runMigration();

        $this->assertFalse(Schema::hasTable('client_contacts'));
        $this->assertFalse(Schema::hasTable('object_contacts'));

        $moved = DB::table('contacts')->where('id', $clientId)->first();
        $this->assertNotNull($moved);
        $this->assertSame('client', $moved->contactable_type);
        $this->assertSame($client->id, $moved->contactable_id);
        $this->assertSame($tenant->id, $moved->tenant_id);
        $this->assertSame('2026-10-01 10:00:00', $moved->created_at);
        $this->assertNull($moved->deleted_at);

        $movedObject = DB::table('contacts')->where('id', $objectId)->first();
        $this->assertNotNull($movedObject);
        $this->assertSame('cleaning_object', $movedObject->contactable_type);
        $this->assertSame($object->id, $movedObject->contactable_id);
        $this->assertSame('2026-10-05 10:00:00', $movedObject->deleted_at);

        $this->assertSame(2, DB::table('activity_log')->where('subject_type', 'App\Models\Contact')->count());
        $this->assertSame(0, DB::table('activity_log')->whereIn('subject_type', ['App\Models\ClientContact', 'App\Models\ObjectContact'])->count());
    }

    public function test_down_restores_both_tables_rows_and_activity_subject_types(): void
    {
        $tenant = Tenant::factory()->create();
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $object = CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);

        $clientContact = Contact::factory()->primary()->for($client, 'contactable')->create(['tenant_id' => $tenant->id]);
        $objectContact = Contact::factory()->for($object, 'contactable')->create(['tenant_id' => $tenant->id]);
        $objectContact->delete();

        $this->rollbackMigration();

        $this->assertFalse(Schema::hasTable('contacts'));
        $restoredClient = DB::table('client_contacts')->where('id', $clientContact->id)->first();
        $this->assertNotNull($restoredClient);
        $this->assertSame($client->id, $restoredClient->client_id);
        $this->assertTrue((bool) $restoredClient->is_primary);

        $restoredObject = DB::table('object_contacts')->where('id', $objectContact->id)->first();
        $this->assertNotNull($restoredObject);
        $this->assertSame($object->id, $restoredObject->cleaning_object_id);
        $this->assertNotNull($restoredObject->deleted_at);

        $this->assertSame(
            'App\Models\ClientContact',
            DB::table('activity_log')->where('subject_id', $clientContact->id)->value('subject_type'),
        );
        $this->assertSame(
            'App\Models\ObjectContact',
            DB::table('activity_log')->where('subject_id', $objectContact->id)->value('subject_type'),
        );
    }
}
