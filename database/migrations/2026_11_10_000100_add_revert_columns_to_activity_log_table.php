<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activity_log', function (Blueprint $table): void {
            $table->timestampTz('reverted_at')->nullable();
            $table->uuid('revert_batch_uuid')->nullable();
            $table->index('batch_uuid');
            $table->index('revert_batch_uuid');
        });
    }

    public function down(): void
    {
        Schema::table('activity_log', function (Blueprint $table): void {
            $table->dropIndex(['batch_uuid']);
            $table->dropIndex(['revert_batch_uuid']);
            $table->dropColumn(['reverted_at', 'revert_batch_uuid']);
        });
    }
};
