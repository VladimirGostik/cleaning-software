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
        Schema::create('clients', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->restrictOnDelete();

            $table->string('type');
            $table->string('name', 255);
            $table->string('ico', 32)->nullable();
            $table->string('dic', 32)->nullable();
            $table->string('vat_number', 32)->nullable();
            $table->boolean('is_vat_payer')->default(false);

            $table->string('street', 255)->nullable();
            $table->string('city', 255)->nullable();
            $table->string('postal_code', 16)->nullable();
            $table->string('country', 255)->default('SK');

            $table->text('note')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'type']);
            $table->index(['tenant_id', 'name']);
            $table->index('ico');
        });

        DB::statement(
            'CREATE UNIQUE INDEX clients_tenant_ico_unique ON clients (tenant_id, ico) WHERE deleted_at IS NULL AND ico IS NOT NULL',
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('clients');
    }
};
