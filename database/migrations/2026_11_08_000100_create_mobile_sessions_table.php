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
        Schema::create('mobile_sessions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('personal_access_token_id')->nullable()->unique()->constrained('personal_access_tokens')->nullOnDelete();
            $table->char('refresh_hash', 64);
            $table->char('previous_refresh_hash', 64)->nullable();
            $table->timestampTz('rotated_at')->nullable();
            $table->timestampTz('rotation_confirmed_at')->nullable();
            $table->timestampTz('expires_at');
            $table->timestamps();

            $table->index('user_id');
            $table->index('expires_at');
        });

        DB::table('personal_access_tokens')->where('abilities', '["mobile"]')->whereNull('expires_at')->delete();
        DB::table('device_tokens')->delete();

        Schema::table('device_tokens', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('personal_access_token_id');
        });

        Schema::table('device_tokens', function (Blueprint $table): void {
            $table->foreignUuid('mobile_session_id')->nullable()->constrained('mobile_sessions')->cascadeOnDelete();
            $table->index('mobile_session_id');
        });
    }

    public function down(): void
    {
        Schema::table('device_tokens', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('mobile_session_id');
        });

        Schema::table('device_tokens', function (Blueprint $table): void {
            $table->foreignId('personal_access_token_id')->nullable()->constrained('personal_access_tokens')->cascadeOnDelete();
        });

        Schema::dropIfExists('mobile_sessions');
    }
};
