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
        Schema::table('expenses', function (Blueprint $table): void {
            $table->foreignUuid('tenant_membership_id')->nullable()->after('tenant_id')->constrained('tenant_memberships')->restrictOnDelete();
            $table->date('payroll_month')->nullable()->after('category');
            $table->boolean('is_undocumented')->default(false)->after('payroll_month');
            $table->index(['tenant_id', 'tenant_membership_id', 'payroll_month'], 'expenses_tenant_employee_month_index');
        });

        DB::statement('ALTER TABLE expenses ADD CONSTRAINT expenses_undocumented_needs_employee CHECK (NOT is_undocumented OR tenant_membership_id IS NOT NULL)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE expenses DROP CONSTRAINT IF EXISTS expenses_undocumented_needs_employee');

        Schema::table('expenses', function (Blueprint $table): void {
            $table->dropIndex('expenses_tenant_employee_month_index');
            $table->dropConstrainedForeignId('tenant_membership_id');
            $table->dropColumn(['payroll_month', 'is_undocumented']);
        });
    }
};
