<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_number_sequences', function (Blueprint $table): void {
            $table->id();

            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->smallInteger('year');
            $table->integer('last_number')->default(0);

            $table->unique(['tenant_id', 'year']);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_number_sequences');
    }
};
