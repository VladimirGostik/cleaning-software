<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const string UNIT = 'mes.';

    public function up(): void
    {
        DB::table('recurring_invoice_items')->whereNull('unit')->update(['unit' => self::UNIT]);
        DB::table('invoice_items')->whereNull('unit')->update(['unit' => self::UNIT]);
    }

    public function down(): void {}
};
