<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->string('constant_symbol', 10)->nullable()->after('note');
            $table->string('specific_symbol', 10)->nullable()->after('constant_symbol');
            $table->string('payment_type')->default('transfer')->after('specific_symbol');
            $table->string('currency', 3)->default('EUR')->after('payment_type');
            $table->string('rounding_mode')->default('none')->after('currency');
            $table->decimal('rounding_amount', 12, 2)->default(0)->after('rounding_mode');
            $table->text('header_text')->nullable()->after('rounding_amount');
            $table->text('footer_text')->nullable()->after('header_text');
            $table->string('supplier_swift')->nullable()->after('supplier_registration_info');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropColumn([
                'constant_symbol',
                'specific_symbol',
                'payment_type',
                'currency',
                'rounding_mode',
                'rounding_amount',
                'header_text',
                'footer_text',
                'supplier_swift',
            ]);
        });
    }
};
