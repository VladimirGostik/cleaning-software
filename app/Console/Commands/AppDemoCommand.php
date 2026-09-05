<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(
    name: 'app:demo',
    description: 'Reset and seed the database with demo data.',
)]
final class AppDemoCommand extends Command
{
    public function handle(): int
    {
        $this->info('Dropping all tables and re-migrating...');
        Artisan::call('migrate:fresh', ['--force' => true], $this->output);

        $this->info('Seeding database with demo data...');
        Artisan::call('db:seed', [
            '--class' => DatabaseSeeder::class,
            '--force' => true,
        ], $this->output);

        $this->info('');
        $this->info('Demo data seeded successfully.');
        $this->info('Admin login: admin@example.com / password');

        return self::SUCCESS;
    }
}
