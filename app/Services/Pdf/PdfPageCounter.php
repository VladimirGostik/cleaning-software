<?php

declare(strict_types=1);

namespace App\Services\Pdf;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Throwable;

final class PdfPageCounter
{
    /** @var array<string, int|null> */
    private array $counted = [];

    /** Null when qpdf cannot read the file (broken, password-protected) or does not answer in time. */
    public function count(string $path): ?int
    {
        $key = $path.'|'.(int) @filesize($path).'|'.(int) @filemtime($path);

        if (! array_key_exists($key, $this->counted)) {
            $this->counted[$key] = $this->run($path);
        }

        return $this->counted[$key];
    }

    private function run(string $path): ?int
    {
        try {
            $result = Process::timeout(config()->integer('ai.assistant.attachments.page_count_timeout_seconds'))
                ->run([config()->string('invoicing.bulk.qpdf_binary'), '--show-npages', $path]);
        } catch (Throwable $exception) {
            Log::warning('pdf.page_count_failed', ['exception' => $exception::class]);

            return null;
        }

        $output = trim($result->output());

        // qpdf exit 3 = succeeded with warnings
        if (! in_array($result->exitCode(), [0, 3], true) || ! ctype_digit($output)) {
            return null;
        }

        return (int) $output;
    }
}
