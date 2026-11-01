<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:grant-platform-admin {email}')]
#[Description('Allow a user to edit global document templates')]
final class GrantPlatformAdmin extends Command
{
    public function handle(): int
    {
        $user = User::query()->where('email', $this->argument('email'))->first();

        if ($user === null) {
            $this->error('User not found.');

            return self::FAILURE;
        }

        $user->forceFill(['is_platform_admin' => true])->save();

        $this->info("{$user->email} is now a platform admin.");

        return self::SUCCESS;
    }
}
