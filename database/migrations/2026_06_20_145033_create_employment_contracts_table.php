<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employment_contracts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('contract_id')->constrained('contracts')->cascadeOnDelete();
            $table->string('employment_type', 32);
            $table->string('position', 255)->nullable();
            $table->decimal('hourly_rate', 10, 2)->nullable();
            $table->decimal('monthly_salary', 10, 2)->nullable();
            $table->decimal('weekly_hours', 5, 2)->nullable();
            $table->date('probation_end_date')->nullable();
            $table->timestamps();

            $table->unique('contract_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employment_contracts');
    }
};
