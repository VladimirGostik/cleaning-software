<?php

declare(strict_types=1);

use App\Actions\GenerateUuid;
use App\Enums\InvoiceStatusEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->decimal('paid_amount', 12, 2)->default(0)->after('deposit');
        });

        $uuid = new GenerateUuid;

        DB::table('invoices')
            ->where('status', InvoiceStatusEnum::Paid->value)
            ->chunkById(200, function ($invoices) use ($uuid): void {
                /** @var stdClass&object{id: string, tenant_id: string, total: string, deposit: string, paid_at: string|null, issue_date: string, payment_type: string} $invoice */
                foreach ($invoices as $invoice) {
                    $amount = number_format(round((float) $invoice->total - (float) $invoice->deposit, 2), 2, '.', '');

                    if ((float) $amount === 0.0) {
                        continue;
                    }

                    DB::table('invoices')->where('id', $invoice->id)->update(['paid_amount' => $amount]);

                    DB::table('invoice_payments')->insert([
                        'id' => $uuid->handle()->toString(),
                        'tenant_id' => $invoice->tenant_id,
                        'invoice_id' => $invoice->id,
                        'amount' => $amount,
                        'paid_on' => Carbon::parse($invoice->paid_at ?? $invoice->issue_date)->toDateString(),
                        'payment_type' => $invoice->payment_type,
                        'source' => 'manual',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropColumn('paid_amount');
        });
    }
};
