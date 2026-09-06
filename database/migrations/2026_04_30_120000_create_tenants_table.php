<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('owner_id')->constrained('users')->restrictOnDelete();

            $table->string('name');
            $table->string('ico', 20)->nullable();
            $table->string('dic', 20)->nullable();
            $table->string('vat_number', 20)->nullable()->comment('IČ DPH');
            $table->boolean('is_vat_payer')->default(false);
            $table->decimal('vat_rate', 5, 2)->default(23);

            $table->string('iban', 34)->nullable();
            $table->string('swift_bic', 11)->nullable();
            $table->string('invoice_number_format')->default('FA-{YYYY}-{XXXX}');
            $table->string('registration_info')->nullable();

            $table->string('address_line')->nullable();
            $table->string('city')->nullable();
            $table->string('postal_code', 16)->nullable();
            $table->string('country', 2)->default('SK');

            $table->string('contact_email')->nullable();
            $table->string('contact_phone', 30)->nullable();

            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index('ico');
            $table->index(['is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
