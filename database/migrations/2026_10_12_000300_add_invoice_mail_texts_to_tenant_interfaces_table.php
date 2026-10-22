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
            $table->string('invoice_mail_subject', 255)->nullable()->after('default_footer_text');
            $table->text('invoice_mail_body')->nullable()->after('invoice_mail_subject');
        });
    }

    public function down(): void
    {
        Schema::table('tenant_interfaces', function (Blueprint $table): void {
            $table->dropColumn(['invoice_mail_subject', 'invoice_mail_body']);
        });
    }
};
