<?php

declare(strict_types=1);

use App\Enums\ContractStatusEnum;
use App\Models\Contract;
use App\Models\Document;
use App\Services\Documents\ContractTextService;
use App\Services\Documents\DocumentLocale;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    public function up(): void
    {
        app(DocumentLocale::class)->run($this->freeze(...));
    }

    private function freeze(): void
    {
        $text = app(ContractTextService::class);
        $previousTenant = app()->bound('current_tenant_id') ? current_tenant_id() : null;

        DB::table('documents')
            ->join('contracts', 'contracts.id', '=', 'documents.documentable_id')
            ->where('documents.documentable_type', (new Contract)->getMorphClass())
            ->where('contracts.status', '!=', ContractStatusEnum::Draft->value)
            ->whereNotNull('documents.body')
            ->select('documents.id as document_id', 'documents.body', 'contracts.id as contract_id', 'contracts.tenant_id')
            ->orderBy('documents.created_at')
            ->each(function (object $row) use ($text): void {
                $stored = Crypt::decryptString(is_string($row->body) ? $row->body : '');

                if (! str_contains($stored, '{{block.')) {
                    return;
                }

                app()->instance('current_tenant_id', $row->tenant_id);

                try {
                    $contract = Contract::withoutGlobalScopes()->where('id', $row->contract_id)->firstOrFail();
                    $contract->setRelation('document', Document::withoutGlobalScopes()->where('id', $row->document_id)->firstOrFail());
                    $contract->setRelation('contractable', $contract->contractable()->withoutGlobalScopes()->withTrashed()->first());

                    $expanded = $text->displayBody($contract);

                    if ($expanded !== null && $expanded !== $stored) {
                        DB::table('documents')->where('id', $row->document_id)->update(['body' => Crypt::encryptString($expanded)]);
                    }
                } catch (Throwable $exception) {
                    Log::warning('Contract blocks were not frozen', ['contract_id' => $row->contract_id, 'error' => $exception->getMessage()]);
                }
            });

        if ($previousTenant !== null) {
            app()->instance('current_tenant_id', $previousTenant);
        } else {
            app()->forgetInstance('current_tenant_id');
        }
    }

    public function down(): void {}
};
