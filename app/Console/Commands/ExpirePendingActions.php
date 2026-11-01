<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Ai\ActionConfirmationService;
use Illuminate\Console\Command;

final class ExpirePendingActions extends Command
{
    protected $signature = 'cleanup:pending-actions';

    protected $description = 'Mark stale pending AI actions as expired';

    public function handle(ActionConfirmationService $actions): int
    {
        $this->info('Expired pending actions: '.$actions->expireStale().'.');

        return self::SUCCESS;
    }
}
