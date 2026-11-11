<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Http\UploadedFile;

trait BuildsContractFiles
{
    protected function contractPdf(string $name = 'zmluva.pdf', string $content = 'one'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, "%PDF-1.4\n".$content);
    }

    protected function contractFixture(string $fixture, ?string $clientName = null): UploadedFile
    {
        $copy = (string) tempnam(sys_get_temp_dir(), 'cf');
        copy(base_path('tests/Fixtures/task-attachments/'.$fixture), $copy);

        return new UploadedFile($copy, $clientName ?? $fixture, null, null, true);
    }

    protected function contractOdt(string $name = 'zmluva.odt'): UploadedFile
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'odt');
        $content = 'application/vnd.oasis.opendocument.text';
        $crc = (int) hexdec(hash('crc32b', $content));
        $header = pack('VvvvvvVVVvv', 0x04034B50, 20, 0, 0, 0, 0, $crc, strlen($content), strlen($content), 8, 0);
        file_put_contents($path, $header.'mimetype'.$content);

        return new UploadedFile($path, $name, null, null, true);
    }
}
