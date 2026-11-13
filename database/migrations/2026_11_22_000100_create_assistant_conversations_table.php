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
        Schema::create('assistant_conversations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->restrictOnDelete();
            $table->text('title');
            $table->timestampTz('last_message_at');
            $table->timestamps(6);

            $table->index(['user_id', 'last_message_at']);
        });

        Schema::create('assistant_messages', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('assistant_conversation_id')->constrained('assistant_conversations')->restrictOnDelete();
            $table->string('role', 16);
            $table->text('content');
            $table->text('attachment_names')->nullable();
            $table->text('actions')->nullable();
            $table->foreignUuid('pending_action_id')->nullable()->constrained('pending_actions')->nullOnDelete();
            $table->text('security_notice')->nullable();
            $table->text('attachment_note')->nullable();
            $table->uuid('client_message_id')->nullable();
            $table->timestamps(6);

            $table->index(['assistant_conversation_id', 'created_at']);
            $table->index('pending_action_id');
        });

        DB::statement('CREATE UNIQUE INDEX assistant_messages_client_message_id_unique ON assistant_messages (client_message_id) WHERE client_message_id IS NOT NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('assistant_messages');
        Schema::dropIfExists('assistant_conversations');
    }
};
