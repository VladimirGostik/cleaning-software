<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\DocumentTemplate;
use App\Scopes\TenantOrGlobalScope;
use Database\Seeders\DocumentTemplateSeeder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'documents:sync-global-templates')]
#[Signature('documents:sync-global-templates {--dry-run : Only report what would change}')]
#[Description('Bring the body of global document templates in line with the seeder definitions.')]
final class SyncGlobalDocumentTemplatesCommand extends Command
{
    public function handle(DocumentTemplateSeeder $seeder): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $updated = 0;

        foreach ($seeder->definitions() as [$name, $type, $employmentType, $body]) {
            $template = DocumentTemplate::query()
                ->withoutGlobalScope(TenantOrGlobalScope::class)
                ->whereNull('tenant_id')
                ->where('type', $type->value)
                ->where('employment_type', $employmentType?->value)
                ->where('name', $name)
                ->first();

            if ($template === null) {
                $this->line("missing (run the seeder): {$name}");

                continue;
            }

            if ($template->body === $body) {
                $this->line("unchanged: {$name}");

                continue;
            }

            if (! $dryRun) {
                $template->update(['body' => $body]);
            }

            $updated++;
            $this->line(($dryRun ? 'would update: ' : 'updated: ').$name);
        }

        $this->info(($dryRun ? 'Would update ' : 'Updated ')."{$updated} global template(s).");

        return self::SUCCESS;
    }
}
