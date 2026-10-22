<?php

declare(strict_types=1);

namespace App\Services\Pdf;

use App\Contracts\MergesPdfDocuments;
use App\Exceptions\PdfMergeFailedException;
use Illuminate\Process\Exceptions\ProcessTimedOutException;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

final readonly class QpdfMerger implements MergesPdfDocuments
{
    public function merge(array $pdfBinaries): string
    {
        if ($pdfBinaries === []) {
            throw new PdfMergeFailedException('No PDF documents to merge.');
        }

        $dir = storage_path('app/tmp/pdf-merge/'.Str::uuid());
        File::ensureDirectoryExists($dir);

        try {
            $inputs = [];

            foreach ($pdfBinaries as $index => $binary) {
                $path = $dir.'/in-'.$index.'.pdf';
                File::put($path, $binary);
                $inputs[] = $path;
            }

            $output = $dir.'/out.pdf';

            try {
                $result = Process::timeout(config()->integer('invoicing.bulk.merge_timeout_seconds'))
                    ->run([config()->string('invoicing.bulk.qpdf_binary'), '--empty', '--pages', ...$inputs, '--', $output]);
            } catch (ProcessTimedOutException $e) {
                throw new PdfMergeFailedException('qpdf timed out merging PDF documents.', 0, $e->getMessage());
            }

            // qpdf exit 3 = succeeded with warnings, output is written
            if (! in_array($result->exitCode(), [0, 3], true) || ! File::exists($output)) {
                throw new PdfMergeFailedException('qpdf failed to merge PDF documents.', (int) $result->exitCode(), $result->errorOutput());
            }

            return File::get($output);
        } finally {
            File::deleteDirectory($dir);
        }
    }
}
