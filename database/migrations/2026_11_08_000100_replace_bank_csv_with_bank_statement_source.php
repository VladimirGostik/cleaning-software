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
        DB::table('expenses')->where('source', 'bank_csv')->update(['source' => 'bank_statement']);

        Schema::table('tenant_interfaces', function (Blueprint $table): void {
            $table->dropColumn('bank_csv_mapping');
        });
    }

    public function down(): void
    {
        Schema::table('tenant_interfaces', function (Blueprint $table): void {
            $table->jsonb('bank_csv_mapping')->nullable();
        });

        DB::table('expenses')->where('source', 'bank_statement')->update(['source' => 'bank_csv']);
    }
};
