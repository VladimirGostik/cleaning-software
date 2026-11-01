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
        Schema::create('pending_actions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->restrictOnDelete();
            $table->string('action', 40);
            $table->string('subject_type', 120);
            $table->uuid('subject_id');
            $table->jsonb('payload');
            $table->char('payload_hash', 64);
            $table->char('subject_hash', 64);
            $table->char('token_hash', 64)->nullable()->unique();
            $table->text('preview');
            $table->string('subject_label', 255);
            $table->string('customer', 255)->nullable();
            $table->string('total', 64)->nullable();
            $table->string('email', 255)->nullable();
            $table->string('channel', 16);
            $table->string('status', 16)->default('pending');
            $table->timestampTz('expires_at');
            $table->timestampTz('executed_at')->nullable();
            $table->string('confirmed_via', 16)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status', 'expires_at']);
            $table->index(['subject_type', 'subject_id']);
        });

        DB::statement("CREATE INDEX pending_actions_stale_idx ON pending_actions (expires_at) WHERE status = 'pending'");
    }

    public function down(): void
    {
        Schema::dropIfExists('pending_actions');
    }
};
