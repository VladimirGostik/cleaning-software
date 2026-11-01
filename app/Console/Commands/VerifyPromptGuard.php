<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Ai\Guard\PromptInjectionCorpus;
use App\Services\Ai\Guard\PromptInjectionDetector;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

final class VerifyPromptGuard extends Command
{
    protected $signature = 'ai:guard-verify';

    protected $description = 'Checks the prompt-injection detector against known attacks and normal business text. Needs no AI key.';

    public function handle(PromptInjectionDetector $detector): int
    {
        $attacks = PromptInjectionCorpus::attacks();
        $benign = PromptInjectionCorpus::benign();

        $missed = array_filter($attacks, static fn (string $sample): bool => ! $detector->scan($sample)->flagged);
        $falsePositives = array_filter($benign, static fn (string $sample): bool => $detector->scan($sample)->flagged);

        $this->line('Attacks detected: '.(count($attacks) - count($missed)).'/'.count($attacks));
        $this->line('Normal text passed: '.(count($benign) - count($falsePositives)).'/'.count($benign));

        foreach ($missed as $sample) {
            $this->error('MISSED: '.Str::limit($sample, 100));
        }

        foreach ($falsePositives as $sample) {
            $this->error('FALSE POSITIVE: '.Str::limit($sample, 100));
        }

        return $missed === [] && $falsePositives === [] ? self::SUCCESS : self::FAILURE;
    }
}
