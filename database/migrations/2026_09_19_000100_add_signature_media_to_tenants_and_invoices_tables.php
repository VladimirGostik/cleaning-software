<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            $table->foreignId('signature_media_id')->nullable()->after('registration_info')
                ->constrained('media')->nullOnDelete();
        });

        Schema::table('invoices', function (Blueprint $table): void {
            $table->foreignId('supplier_signature_media_id')->nullable()->after('supplier_registration_info')
                ->constrained('media')->nullOnDelete();
            $table->string('issued_by_name', 255)->nullable()->after('issued_at');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('supplier_signature_media_id');
            $table->dropColumn('issued_by_name');
        });

        Schema::table('tenants', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('signature_media_id');
        });
    }
};
