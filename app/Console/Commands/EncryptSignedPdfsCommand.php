<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Document;
use App\Models\Media;
use App\Services\Documents\SignedPdfStore;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'app:encrypt-signed-pdfs')]
#[Signature('app:encrypt-signed-pdfs {--dry-run : Only report what would be encrypted}')]
#[Description('Encrypt signed contract PDF snapshots that were stored as plain PDF.')]
final class EncryptSignedPdfsCommand extends Command
{
    public function handle(SignedPdfStore $store): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $upgraded = [];
        $alreadyEncrypted = 0;
        $repaired = 0;

        Media::query()
            ->where('model_type', (new Document)->getMorphClass())
            ->where('collection_name', SignedPdfStore::COLLECTION)
            ->lazyById()
            ->each(function (Media $media) use ($store, $dryRun, &$upgraded, &$alreadyEncrypted, &$repaired): void {
                if ($store->isEncrypted($media)) {
                    $alreadyEncrypted++;

                    if (! $dryRun && $store->syncMetadata($media)) {
                        $repaired++;
                    }

                    return;
                }

                if (! $dryRun) {
                    $store->encryptInPlace($media);
                }

                $tenantId = (string) $media->tenant_id;
                $upgraded[$tenantId] = ($upgraded[$tenantId] ?? 0) + 1;
            });

        foreach ($upgraded as $tenantId => $count) {
            $this->line("tenant {$tenantId}: {$count}");
        }

        $verb = $dryRun ? 'Would encrypt' : 'Encrypted';
        $this->info("{$verb} ".array_sum($upgraded)." plain PDF(s); {$alreadyEncrypted} already encrypted; {$repaired} metadata repaired.");

        return self::SUCCESS;
    }
}
