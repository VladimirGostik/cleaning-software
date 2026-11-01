<?php

declare(strict_types=1);

namespace Tests\Feature\Contracts;

use App\Models\CleaningObject;
use App\Models\Permission;
use App\Models\Quote;
use App\Models\Role;
use App\Models\Tenant;
use Database\Seeders\PermissionSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

final class ContractLegacyBodyMigrationTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<string> */
    private const array FILES = [
        '2026_10_31_000100_create_document_templates_table.php',
        '2026_10_31_000200_create_documents_table.php',
        '2026_10_31_000400_rename_template_permissions_and_alias_client_morph.php',
    ];

    private function migration(string $file): Migration
    {
        /** @var Migration $migration */
        $migration = require database_path('migrations/'.$file);

        return $migration;
    }

    private function rollBack(): void
    {
        foreach (array_reverse(self::FILES) as $file) {
            $this->migration($file)->down();
        }
    }

    private function migrateForward(): void
    {
        foreach (self::FILES as $file) {
            $this->migration($file)->up();
        }
    }

    public function test_legacy_body_and_templates_survive_in_documents(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);
        $object = CleaningObject::factory()->create(['tenant_id' => $tenant->id]);

        $this->rollBack();

        $templateId = (string) Str::uuid7();
        DB::table('contract_templates')->insert([
            'id' => $templateId, 'tenant_id' => $tenant->id, 'name' => 'Stará šablóna', 'category' => 'service_agreement',
            'body' => "Prvý odsek <b>nebezpečný</b>\n\nDruhý odsek {{quote.items}}", 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $contractId = (string) Str::uuid7();
        $deletedId = (string) Str::uuid7();
        foreach ([[$contractId, null], [$deletedId, now()]] as [$id, $deletedAt]) {
            DB::table('contracts')->insert([
                'id' => $id, 'tenant_id' => $tenant->id, 'contract_template_id' => $templateId,
                'contractable_type' => 'cleaning_object', 'contractable_id' => $object->id,
                'category' => 'service_agreement', 'status' => 'active', 'term_type' => 'indefinite',
                'title' => 'Zmluva', 'body' => "Zmluvné podmienky pravidelného upratovania.\nDruhý riadok <script>x</script>",
                'valid_from' => now()->toDateString(), 'created_at' => now(), 'updated_at' => now(), 'deleted_at' => $deletedAt,
            ]);
        }
        DB::table('activity_log')->insert([
            'log_name' => 'default', 'description' => 'created',
            'subject_type' => 'App\Models\Client', 'subject_id' => (string) Str::uuid7(), 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->migrateForward();

        foreach ([$contractId, $deletedId] as $id) {
            $encrypted = (string) DB::table('documents')->where('documentable_id', $id)->where('documentable_type', 'App\Models\Contract')->value('body');
            $this->assertStringNotContainsString('Zmluvné podmienky', $encrypted);
            $body = Crypt::decryptString($encrypted);
            $this->assertStringContainsString('Zmluvné podmienky pravidelného upratovania.', $body);
            $this->assertStringStartsWith('<p>{{block.parties}}</p>', $body);
            $this->assertStringEndsWith('<p>{{block.signatures}}</p>', $body);
            $this->assertStringNotContainsString('<script>', $body);
            $this->assertStringContainsString('&lt;script&gt;', $body);
        }
        $this->assertSame(1, DB::table('documents')->where('documentable_id', $contractId)->count());
        $this->assertNotNull(DB::table('documents')->where('documentable_id', $deletedId)->value('deleted_at'));

        $template = DB::table('document_templates')->where('id', $templateId)->first();
        $this->assertNotNull($template);
        $this->assertSame('service_agreement', $template->type);
        $this->assertSame('<p>Prvý odsek &lt;b&gt;nebezpečný&lt;/b&gt;</p><p>Druhý odsek {{block.quote_items}}</p>', $template->body);
        $this->assertSame($templateId, DB::table('contracts')->where('id', $contractId)->value('document_template_id'));

        $this->assertFalse(Schema::hasColumn('contracts', 'body'));
        $this->assertFalse(Schema::hasTable('contract_templates'));
        $this->assertDatabaseHas('activity_log', ['subject_type' => 'client']);
        $this->assertDatabaseMissing('activity_log', ['subject_type' => 'App\Models\Client']);
    }

    public function test_uploaded_quote_files_move_onto_a_document(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);
        $quote = Quote::factory()->document()->create(['tenant_id' => $tenant->id]);

        $this->rollBack();

        DB::table('media')->insert([
            'model_type' => 'App\Models\Quote', 'model_id' => $quote->id, 'tenant_id' => $tenant->id, 'uuid' => (string) Str::uuid(),
            'collection_name' => 'document', 'name' => 'ponuka', 'file_name' => 'ponuka.pdf', 'mime_type' => 'application/pdf',
            'disk' => 'local', 'size' => 10, 'manipulations' => '[]', 'custom_properties' => '[]',
            'generated_conversions' => '[]', 'responsive_images' => '[]', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->migrateForward();

        $documentId = DB::table('documents')->where('documentable_id', $quote->id)->value('id');
        $this->assertNotNull($documentId);
        $this->assertDatabaseHas('media', [
            'model_type' => 'App\Models\Document', 'model_id' => $documentId, 'collection_name' => 'file', 'file_name' => 'ponuka.pdf',
        ]);
    }

    public function test_permission_migration_keeps_customized_roles_and_only_adds(): void
    {
        $tenant = Tenant::factory()->create();
        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);
        $this->seed(PermissionSeeder::class);

        $this->rollBack();

        $this->assertDatabaseHas('permissions', ['name' => 'view contract_templates']);
        $this->assertDatabaseMissing('permissions', ['name' => 'view employee_documents']);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);
        $customized = Role::findOrCreate('Sekretárka', 'web');
        $customized->givePermissionTo(['view clients', 'view contract_templates']);
        $admin = Role::findOrCreate('Admin', 'web');
        $admin->givePermissionTo(['view clients']);
        $accountant = Role::findOrCreate('Účtovníčka', 'web');
        $accountant->givePermissionTo(['view invoices']);

        $this->migrateForward();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $names = fn (string $role): array => Role::query()->where('name', $role)->firstOrFail()->permissions()->pluck('name')->sort()->values()->all();

        $this->assertSame(['view clients', 'view document_templates'], $names('Sekretárka'));
        $this->assertSame(
            ['manage employee_documents', 'view clients', 'view employee_documents', 'view employee_personal_data'],
            $names('Admin'),
        );
        $this->assertSame(['view employee_personal_data', 'view invoices'], $names('Účtovníčka'));
        $this->assertSame(1, Permission::query()->where('name', 'view document_templates')->count());
        $this->assertSame(0, Permission::query()->where('name', 'view contract_templates')->count());
    }
}
