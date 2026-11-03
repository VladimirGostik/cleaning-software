<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quote_items', function (Blueprint $table): void {
            $table->string('billing_period')->default('one_off')->after('frequency');
        });

        Schema::table('quotes', function (Blueprint $table): void {
            $table->jsonb('period_totals')->nullable()->after('vat_breakdown');
            $table->text('included_text')->nullable()->after('note');
        });

        DB::statement(<<<'SQL'
            UPDATE quotes SET period_totals = jsonb_build_array(jsonb_build_object(
                'billing_period', 'one_off',
                'subtotal', subtotal::text,
                'vat_amount', vat_amount::text,
                'total', total::text
            ))
            WHERE kind = 'itemized'
            AND EXISTS (SELECT 1 FROM quote_items WHERE quote_items.quote_id = quotes.id)
        SQL);
    }

    public function down(): void
    {
        Schema::table('quotes', function (Blueprint $table): void {
            $table->dropColumn(['period_totals', 'included_text']);
        });

        Schema::table('quote_items', function (Blueprint $table): void {
            $table->dropColumn('billing_period');
        });
    }
};
