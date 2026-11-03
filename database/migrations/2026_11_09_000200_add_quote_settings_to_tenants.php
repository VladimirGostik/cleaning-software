<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            $table->string('signing_place', 100)->nullable();
        });

        Schema::table('tenant_interfaces', function (Blueprint $table): void {
            $table->text('default_quote_included_text')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('tenant_interfaces', function (Blueprint $table): void {
            $table->dropColumn('default_quote_included_text');
        });

        Schema::table('tenants', function (Blueprint $table): void {
            $table->dropColumn('signing_place');
        });
    }
};
