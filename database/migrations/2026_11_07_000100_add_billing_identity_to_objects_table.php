<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('objects', function (Blueprint $table): void {
            $table->string('billing_name')->nullable();
            $table->string('billing_ico', 32)->nullable();
            $table->string('billing_dic', 32)->nullable();
            $table->string('billing_vat_number', 32)->nullable();
            $table->string('billing_peppol_participant_id', 64)->nullable();
            $table->string('billing_street')->nullable();
            $table->string('billing_city')->nullable();
            $table->string('billing_postal_code', 16)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('objects', function (Blueprint $table): void {
            $table->dropColumn([
                'billing_name', 'billing_ico', 'billing_dic', 'billing_vat_number',
                'billing_peppol_participant_id', 'billing_street', 'billing_city', 'billing_postal_code',
            ]);
        });
    }
};
