<?php

declare(strict_types=1);

use App\Enums\DocumentTypeEnum;
use App\Models\DocumentTemplate;
use Database\Seeders\DocumentTemplateSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

// Forward-only data migration (no `down` restores data, like 2026_10_31_000500); soft-deleted firm rows stay recoverable.
return new class extends Migration
{
    public function up(): void
    {
        $body = collect((new DocumentTemplateSeeder)->definitions())
            ->first(fn (array $definition): bool => $definition[1] === DocumentTypeEnum::Quote)[3] ?? null;

        $global = 0;

        if (is_string($body)) {
            foreach (DocumentTemplate::query()->withoutGlobalScopes()->whereNull('tenant_id')->where('type', DocumentTypeEnum::Quote->value)->get() as $template) {
                if ($template->body !== $body) {
                    $template->update(['body' => $body]);
                    $global++;
                }
            }
        }

        $firmIds = DocumentTemplate::query()->withoutGlobalScopes()->withTrashed()
            ->whereNotNull('tenant_id')
            ->where('type', DocumentTypeEnum::Quote->value)
            ->pluck('id')
            ->all();

        $globalId = DocumentTemplate::query()->withoutGlobalScopes()
            ->whereNull('tenant_id')
            ->whereNull('deleted_at')
            ->where('type', DocumentTypeEnum::Quote->value)
            ->orderBy('created_at')
            ->value('id');

        $repointed = DB::table('documents')->whereIn('document_template_id', $firmIds)->update(['document_template_id' => $globalId]);

        $firm = DocumentTemplate::query()->withoutGlobalScopes()
            ->whereNotNull('tenant_id')
            ->where('type', DocumentTypeEnum::Quote->value)
            ->whereNull('deleted_at')
            ->update(['deleted_at' => now()]);

        Log::info('Quote templates replaced by the KEGOS default', ['global_updated' => $global, 'firm_soft_deleted' => $firm, 'documents_repointed' => $repointed]);
    }
};
