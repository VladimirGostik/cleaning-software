<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('owner_id')->constrained('users')->cascadeOnDelete();

            $table->string('name');
            $table->string('ico')->nullable();
            $table->string('dic')->nullable();
            $table->string('vat_number')->nullable()->comment('IČ DPH');
            $table->boolean('is_vat_payer')->default(false);
            $table->decimal('vat_rate', 5, 2)->default(23);
            $table->string('iban')->nullable();
            $table->string('invoice_number_format')->default('FA-{YYYY}-{XXXX}');
            $table->string('registration_info')->nullable();

            $table->string('address_line')->nullable();
            $table->string('city')->nullable();
            $table->string('postal_code', 16)->nullable();
            $table->string('country', 2)->default('SK');

            $table->string('contact_email')->nullable();
            $table->string('contact_phone')->nullable();

            $table->boolean('is_active')->default(true);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['ico']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
