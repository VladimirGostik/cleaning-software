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
        Schema::create('notifications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('type', 64);
            $table->string('notifiable_type');
            $table->uuid('notifiable_id');
            $table->foreignUuid('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->jsonb('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index('tenant_id');
            $table->index(['notifiable_type', 'notifiable_id', 'tenant_id', 'created_at'], 'notifications_recipient_tenant_created_idx');
        });

        DB::statement('CREATE INDEX notifications_recipient_tenant_unread_idx ON notifications (notifiable_type, notifiable_id, tenant_id) WHERE read_at IS NULL');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS notifications_recipient_tenant_unread_idx');
        Schema::dropIfExists('notifications');
    }
};
