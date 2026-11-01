<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\MobileSessionService;
use Illuminate\Console\Command;

final class PruneMobileSessions extends Command
{
    protected $signature = 'cleanup:mobile-sessions';

    protected $description = 'Delete mobile sessions that passed their refresh expiry, with their push registrations';

    public function handle(MobileSessionService $sessions): int
    {
        $this->info('Pruned mobile sessions: '.$sessions->pruneExpired().'.');

        return self::SUCCESS;
    }
}
