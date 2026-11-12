<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotes', function (Blueprint $table): void {
            $table->boolean('show_totals')->default(true);
            $table->boolean('show_vat_note')->default(true);
            $table->string('customer_represented_by')->nullable();
            $table->string('customer_ico')->nullable();
            $table->string('customer_dic')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('quotes', function (Blueprint $table): void {
            $table->dropColumn(['show_totals', 'show_vat_note', 'customer_represented_by', 'customer_ico', 'customer_dic']);
        });
    }
};
