<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_memberships', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignUuid('tenant_id')->constrained('tenants')->restrictOnDelete();

            $table->boolean('is_active')->default(true);
            $table->timestamp('joined_at')->useCurrent();

            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('position')->nullable();

            $table->timestamps();

            $table->unique(['user_id', 'tenant_id']);
            $table->index(['tenant_id', 'is_active']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_memberships');
    }
};
