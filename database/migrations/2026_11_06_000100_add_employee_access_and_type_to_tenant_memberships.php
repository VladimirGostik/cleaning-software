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
        Schema::table('tenant_memberships', function (Blueprint $table): void {
            $table->uuid('user_id')->nullable()->change();
            $table->string('employee_type', 32)->nullable()->after('position');
            $table->string('contact_email')->nullable()->after('employee_type');
            $table->string('company_name')->nullable()->after('company_ico');
        });

        DB::statement(<<<'SQL'
            UPDATE tenant_memberships AS m
            SET employee_type = CASE latest.employment_type
                WHEN 'tpp' THEN 'tpp'
                WHEN 'dpp' THEN 'brigade'
                WHEN 'dpc' THEN 'brigade'
                WHEN 'self_employed' THEN 'self_employed'
            END
            FROM (
                SELECT DISTINCT ON (c.contractable_id) c.contractable_id, ec.employment_type
                FROM contracts AS c
                JOIN employment_contracts AS ec ON ec.contract_id = c.id
                WHERE c.contractable_type = 'tenant_membership'
                    AND c.category = 'employment'
                    AND c.deleted_at IS NULL
                ORDER BY c.contractable_id, c.created_at DESC
            ) AS latest
            WHERE latest.contractable_id = m.id
                AND m.employee_type IS NULL
        SQL);

        DB::statement(<<<'SQL'
            UPDATE tenant_invitations
            SET status = 'revoked', updated_at = now()
            WHERE status = 'pending'
                AND deleted_at IS NULL
                AND EXISTS (
                    SELECT 1
                    FROM tenant_memberships AS m
                    JOIN users AS u ON u.id = m.user_id
                    WHERE m.tenant_id = tenant_invitations.tenant_id
                        AND m.is_active = false
                        AND lower(u.email) = lower(tenant_invitations.email)
                )
        SQL);
    }

    public function down(): void
    {
        // user_id stays nullable: rows without a login may exist by now and cannot be restored to NOT NULL.
        Schema::table('tenant_memberships', function (Blueprint $table): void {
            $table->dropColumn(['employee_type', 'contact_email', 'company_name']);
        });
    }
};
