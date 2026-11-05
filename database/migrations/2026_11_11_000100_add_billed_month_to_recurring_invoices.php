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
            $table->string('billed_month')->default('none')->after('frequency');
        });
    }

    public function down(): void
    {
        Schema::table('recurring_invoices', function (Blueprint $table): void {
            $table->dropColumn('billed_month');
        });
    }
};
