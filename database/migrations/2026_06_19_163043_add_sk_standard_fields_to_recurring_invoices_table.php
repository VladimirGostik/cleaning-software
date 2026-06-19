<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recurring_invoices', function (Blueprint $table): void {
            $table->string('constant_symbol', 10)->nullable()->after('note');
            $table->string('payment_type')->default('transfer')->after('constant_symbol');
            $table->string('currency', 3)->default('EUR')->after('payment_type');
            $table->string('rounding_mode')->default('none')->after('currency');
            $table->text('header_text')->nullable()->after('rounding_mode');
            $table->text('footer_text')->nullable()->after('header_text');
        });
    }

    public function down(): void
    {
        Schema::table('recurring_invoices', function (Blueprint $table): void {
            $table->dropColumn([
                'constant_symbol',
                'payment_type',
                'currency',
                'rounding_mode',
                'header_text',
                'footer_text',
            ]);
        });
    }
};
