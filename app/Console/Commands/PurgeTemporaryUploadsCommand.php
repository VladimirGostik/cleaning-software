<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\TemporaryUploadService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'app:purge-temporary-uploads')]
#[Signature('app:purge-temporary-uploads {--hours=24 : Delete uploads older than this many hours}')]
#[Description('Delete temporary uploads older than the specified number of hours.')]
final class PurgeTemporaryUploadsCommand extends Command
{
    public function handle(TemporaryUploadService $service): int
    {
        $hours = (int) $this->option('hours');

        $deleted = $service->purgeOlderThan($hours);

        $this->info("Purged {$deleted} temporary upload(s) older than {$hours} hour(s).");

        return self::SUCCESS;
    }
}
