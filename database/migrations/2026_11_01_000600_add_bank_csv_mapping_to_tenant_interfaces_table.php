<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenant_interfaces', function (Blueprint $table): void {
            $table->jsonb('bank_csv_mapping')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('tenant_interfaces', function (Blueprint $table): void {
            $table->dropColumn('bank_csv_mapping');
        });
    }
};
