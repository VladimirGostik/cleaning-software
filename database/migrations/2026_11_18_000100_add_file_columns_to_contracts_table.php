<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contracts', function (Blueprint $table): void {
            $table->string('source', 16)->default('generated');
            $table->boolean('is_signed')->default(false);
            $table->char('file_sha256', 64)->nullable();
        });

        DB::statement('UPDATE contracts SET is_signed = true WHERE signed_at IS NOT NULL');
        DB::statement('CREATE UNIQUE INDEX contracts_tenant_file_sha256_unique ON contracts (tenant_id, file_sha256) WHERE file_sha256 IS NOT NULL AND deleted_at IS NULL');
        DB::statement('ALTER TABLE contracts ADD CONSTRAINT contracts_signed_at_requires_signed CHECK (signed_at IS NULL OR is_signed)');
        DB::statement("ALTER TABLE contracts ADD CONSTRAINT contracts_file_hash_matches_source CHECK ((source = 'file') = (file_sha256 IS NOT NULL))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE contracts DROP CONSTRAINT IF EXISTS contracts_file_hash_matches_source');
        DB::statement('ALTER TABLE contracts DROP CONSTRAINT IF EXISTS contracts_signed_at_requires_signed');
        DB::statement('DROP INDEX IF EXISTS contracts_tenant_file_sha256_unique');

        Schema::table('contracts', function (Blueprint $table): void {
            $table->dropColumn(['source', 'is_signed', 'file_sha256']);
        });
    }
};
