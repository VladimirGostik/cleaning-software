<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const array COLUMNS = ['id', 'tenant_id', 'name', 'position', 'email', 'phone', 'is_primary', 'created_at', 'updated_at', 'deleted_at'];

    public function up(): void
    {
        Schema::create('contacts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->string('contactable_type');
            $table->uuid('contactable_id');

            $table->string('name', 255);
            $table->string('position', 255)->nullable();
            $table->string('email', 255)->nullable();
            $table->string('phone', 64)->nullable();
            $table->boolean('is_primary')->default(false);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['contactable_type', 'contactable_id', 'is_primary']);
        });

        $columns = implode(', ', self::COLUMNS);

        foreach ([['client_contacts', 'client', 'client_id'], ['object_contacts', 'cleaning_object', 'cleaning_object_id']] as [$table, $alias, $parentColumn]) {
            DB::statement("INSERT INTO contacts ({$columns}, contactable_type, contactable_id) SELECT {$columns}, '{$alias}', {$parentColumn} FROM {$table} ORDER BY created_at, id");
        }

        DB::table('activity_log')
            ->whereIn('subject_type', ['App\Models\ClientContact', 'App\Models\ObjectContact'])
            ->update(['subject_type' => 'App\Models\Contact']);

        Schema::drop('client_contacts');
        Schema::drop('object_contacts');
    }

    public function down(): void
    {
        $this->createLegacyTable('client_contacts', 'client_id', 'clients');
        $this->createLegacyTable('object_contacts', 'cleaning_object_id', 'objects');

        $columns = implode(', ', self::COLUMNS);

        foreach ([['client_contacts', 'client', 'client_id', 'App\Models\ClientContact'], ['object_contacts', 'cleaning_object', 'cleaning_object_id', 'App\Models\ObjectContact']] as [$table, $alias, $parentColumn, $oldClass]) {
            DB::statement("INSERT INTO {$table} ({$columns}, {$parentColumn}) SELECT {$columns}, contactable_id FROM contacts WHERE contactable_type = '{$alias}' ORDER BY created_at, id");

            DB::statement(
                "UPDATE activity_log SET subject_type = '{$oldClass}' WHERE subject_type = 'App\\Models\\Contact' AND subject_id IN (SELECT id FROM contacts WHERE contactable_type = '{$alias}')",
            );
        }

        Schema::drop('contacts');
    }

    private function createLegacyTable(string $name, string $parentColumn, string $parentTable): void
    {
        Schema::create($name, function (Blueprint $table) use ($parentColumn, $parentTable): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignUuid($parentColumn)->constrained($parentTable)->restrictOnDelete();

            $table->string('name', 255);
            $table->string('position', 255)->nullable();
            $table->string('email', 255)->nullable();
            $table->string('phone', 64)->nullable();
            $table->boolean('is_primary')->default(false);

            $table->timestamps();
            $table->softDeletes();

            $table->index([$parentColumn, 'is_primary']);
        });
    }
};
