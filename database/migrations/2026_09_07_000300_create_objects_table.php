<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('objects', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignUuid('client_id')->constrained('clients')->restrictOnDelete();

            $table->string('type');
            $table->string('name', 255);
            $table->string('street', 255)->nullable();
            $table->string('city', 255)->nullable();
            $table->string('postal_code', 16)->nullable();
            $table->string('country', 255)->default('SK');

            $table->string('access_code', 64)->nullable();
            $table->string('key_box_code', 64)->nullable();
            $table->unsignedSmallInteger('key_count')->nullable();
            $table->text('special_instructions')->nullable();
            $table->decimal('area_sqm', 10, 2)->nullable();
            $table->smallInteger('floor')->nullable();
            $table->boolean('is_active')->default(true);

            // Reserved for a future map view — no UI consumer yet.
            $table->decimal('gps_lat', 10, 7)->nullable();
            $table->decimal('gps_lng', 10, 7)->nullable();

            $table->timestamps();
            // D1 override: an object has two lifecycle switches — `is_active` (direct user
            // deactivation) and `deleted_at` (cascade soft-delete when its client is destroyed).
            $table->softDeletes();

            $table->index(['tenant_id', 'client_id']);
            $table->index(['tenant_id', 'type']);
            $table->index(['tenant_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('objects');
    }
};
