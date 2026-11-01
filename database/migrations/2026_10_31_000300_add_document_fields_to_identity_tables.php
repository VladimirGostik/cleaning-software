<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('is_platform_admin')->default(false);
        });

        Schema::table('tenant_memberships', function (Blueprint $table): void {
            $table->date('birth_date')->nullable();
            $table->text('national_id')->nullable();
            $table->string('street', 255)->nullable();
            $table->string('city', 255)->nullable();
            $table->string('postal_code', 32)->nullable();
            $table->string('company_ico', 32)->nullable();
        });

        Schema::table('tenants', function (Blueprint $table): void {
            $table->string('representative_name', 255)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            $table->dropColumn('representative_name');
        });

        Schema::table('tenant_memberships', function (Blueprint $table): void {
            $table->dropColumn(['birth_date', 'national_id', 'street', 'city', 'postal_code', 'company_ico']);
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('is_platform_admin');
        });
    }
};
