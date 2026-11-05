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
            $table->string('billing_email', 255)->nullable()->after('billing_postal_code');
        });
    }

    public function down(): void
    {
        Schema::table('objects', function (Blueprint $table): void {
            $table->dropColumn('billing_email');
        });
    }
};
