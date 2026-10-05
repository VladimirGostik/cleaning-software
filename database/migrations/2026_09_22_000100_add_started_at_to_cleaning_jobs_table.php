<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cleaning_jobs', function (Blueprint $table): void {
            $table->timestamp('started_at')->nullable()->after('end_time');
        });
    }

    public function down(): void
    {
        Schema::table('cleaning_jobs', function (Blueprint $table): void {
            $table->dropColumn('started_at');
        });
    }
};
