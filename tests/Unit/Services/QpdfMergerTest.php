<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Exceptions\PdfMergeFailedException;
use App\Services\Pdf\QpdfMerger;
use Illuminate\Process\Exceptions\ProcessTimedOutException;
use Illuminate\Process\FakeProcessResult;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Symfony\Component\Process\Exception\ProcessTimedOutException as SymfonyTimedOut;
use Symfony\Component\Process\Process as SymfonyProcess;
use Tests\TestCase;

final class QpdfMergerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (trim((string) shell_exec('command -v qpdf')) === '') {
            $this->markTestSkipped('qpdf binary not available.');
        }
    }

    private function minimalPdf(): string
    {
        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 200 200] /Resources << >> >>',
        ];

        $pdf = "%PDF-1.4\n";
        $offsets = [];

        foreach ($objects as $i => $body) {
            $offsets[] = strlen($pdf);
            $pdf .= ($i + 1)." 0 obj\n".$body."\nendobj\n";
        }

        $xref = strlen($pdf);
        $pdf .= "xref\n0 4\n0000000000 65535 f \n";

        foreach ($offsets as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }

        return $pdf."trailer\n<< /Size 4 /Root 1 0 R >>\nstartxref\n".$xref."\n%%EOF\n";
    }

    private function pageCount(string $binary): int
    {
        $path = tempnam(sys_get_temp_dir(), 'qpdf');
        file_put_contents($path, $binary);
        $count = (int) trim((string) shell_exec('qpdf --show-npages '.escapeshellarg($path)));
        unlink($path);

        return $count;
    }

    public function test_merges_two_pdfs_into_two_pages_and_cleans_temp_dir(): void
    {
        $merged = (new QpdfMerger)->merge([$this->minimalPdf(), $this->minimalPdf()]);

        $this->assertSame(2, $this->pageCount($merged));
        $this->assertSame([], File::directories(storage_path('app/tmp/pdf-merge')));
    }

    public function test_invalid_input_throws_merge_failed_exception_and_cleans_temp_dir(): void
    {
        try {
            (new QpdfMerger)->merge([$this->minimalPdf(), 'not a pdf']);
            $this->fail('Expected PdfMergeFailedException.');
        } catch (PdfMergeFailedException $e) {
            $this->assertNotSame(0, $e->exitCode);
            $this->assertNotSame('', $e->stderr);
        }

        $this->assertSame([], File::directories(storage_path('app/tmp/pdf-merge')));
    }

    public function test_empty_input_throws(): void
    {
        $this->expectException(PdfMergeFailedException::class);

        (new QpdfMerger)->merge([]);
    }

    public function test_timeout_is_rethrown_as_merge_failed_and_cleans_temp_dir(): void
    {
        Process::fake(function (): never {
            throw new ProcessTimedOutException(
                new SymfonyTimedOut(new SymfonyProcess(['qpdf']), SymfonyTimedOut::TYPE_GENERAL),
                new FakeProcessResult,
            );
        });

        try {
            (new QpdfMerger)->merge([$this->minimalPdf()]);
            $this->fail('Expected PdfMergeFailedException.');
        } catch (PdfMergeFailedException) {
            $this->assertSame([], File::directories(storage_path('app/tmp/pdf-merge')));
        }
    }
}
