<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenant_interfaces', function (Blueprint $table): void {
            $table->text('default_header_text')->nullable()->after('default_rounding_mode');
            $table->text('default_footer_text')->nullable()->after('default_header_text');
        });
    }

    public function down(): void
    {
        Schema::table('tenant_interfaces', function (Blueprint $table): void {
            $table->dropColumn(['default_header_text', 'default_footer_text']);
        });
    }
};
