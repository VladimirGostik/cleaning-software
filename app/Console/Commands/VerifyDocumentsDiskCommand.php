<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Throwable;

#[AsCommand(name: 'documents:verify-disk')]
#[Signature('documents:verify-disk')]
#[Description('Write, read back, link and delete a probe file on the configured documents disk.')]
final class VerifyDocumentsDiskCommand extends Command
{
    public function handle(): int
    {
        $name = config('documents.disk');
        $name = is_string($name) ? $name : 'local';

        try {
            /** @var FilesystemAdapter $disk */
            $disk = Storage::disk($name);
        } catch (Throwable $e) {
            $this->reportFailure('disk', $e);

            return self::FAILURE;
        }

        $driver = config("filesystems.disks.{$name}.driver");
        $this->line('Documents disk: '.$name.' ('.(is_string($driver) ? $driver : 'unknown').')');

        $path = 'documents-verify/'.Str::uuid()->toString().'.pdf';
        $payload = '%PDF-verify-'.Str::random(32);
        $ok = true;

        try {
            $ok = $this->step('write', function () use ($disk, $path, $payload): void {
                $disk->put($path, $payload);
            });

            $ok = $ok && $this->step('exists', function () use ($disk, $path): void {
                if (! $disk->exists($path)) {
                    throw new RuntimeException('file not found after write');
                }
            });

            $ok = $ok && $this->step('read back', function () use ($disk, $path, $payload): void {
                if ($disk->get($path) !== $payload) {
                    throw new RuntimeException('content differs from what was written');
                }
            });

            if ($ok) {
                $ok = $this->temporaryUrlStep($disk, $path, $payload, $driver === 's3');
            }
        } finally {
            $ok = $this->step('delete', function () use ($disk, $path): void {
                $disk->delete($path);

                if ($disk->exists($path)) {
                    throw new RuntimeException('file still present after delete');
                }
            }) && $ok;
        }

        if (! $ok) {
            $this->error('Documents disk verification FAILED.');

            return self::FAILURE;
        }

        $this->info('Documents disk verified.');

        return self::SUCCESS;
    }

    private function temporaryUrlStep(FilesystemAdapter $disk, string $path, string $payload, bool $fetch): bool
    {
        if (! $disk->providesTemporaryUrls()) {
            $this->line('  [skip] temporary url: driver does not support it');

            return true;
        }

        return $this->step('temporary url', function () use ($disk, $path, $payload, $fetch): void {
            $url = $disk->temporaryUrl($path, now()->addMinutes(2));

            if (! $fetch) {
                return;
            }

            $response = Http::timeout(10)->get($url);

            if (! $response->successful() || $response->body() !== $payload) {
                throw new RuntimeException('presigned url did not return the file (HTTP '.$response->status().')');
            }
        });
    }

    private function step(string $label, callable $run): bool
    {
        try {
            $run();
        } catch (Throwable $e) {
            return $this->reportFailure($label, $e);
        }

        $this->line("  [pass] {$label}");

        return true;
    }

    private function redact(string $message): string
    {
        return (string) preg_replace(['/\?\S*/', '/X-Amz-[A-Za-z-]+=[^&\s]*/i'], '', $message);
    }

    private function reportFailure(string $label, Throwable $e): bool
    {
        $this->line("  [FAIL] {$label}: ".class_basename($e).' - '.Str::limit($this->redact(strtok($e->getMessage(), "\n") ?: ''), 200));

        return false;
    }
}
