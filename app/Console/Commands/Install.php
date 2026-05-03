<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:install {--with-keys : Generate application key} {--fresh : Run migrate:fresh --seed instead of migrate + db:seed}')]
#[Description('Install or update the CleanMaster application')]
final class Install extends Command
{
    public function handle(): void
    {
        if ($this->option('with-keys')) {
            $this->call('key:generate');
        }

        if ($this->option('fresh')) {
            $this->call('migrate:fresh', ['--force' => true, '--seed' => true]);
        } else {
            $this->call('migrate', ['--force' => true]);
            $this->call('db:seed', ['--force' => true]);
        }

        $this->call('storage:link', ['--force' => true]);
        $this->call('optimize:clear');
        $this->call('optimize');

        $this->info('CleanMaster installed successfully.');
    }
}
