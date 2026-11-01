<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Http\UploadedFile;

trait BuildsTaskAttachmentFiles
{
    protected function attachmentFile(string $fixture, ?string $clientName = null): UploadedFile
    {
        $source = base_path('tests/Fixtures/task-attachments/'.$fixture);
        $copy = tempnam(sys_get_temp_dir(), 'att');
        copy($source, (string) $copy);

        return new UploadedFile((string) $copy, $clientName ?? $fixture, null, null, true);
    }

    protected function attachmentBytes(string $name, int $kilobytes): UploadedFile
    {
        $copy = (string) tempnam(sys_get_temp_dir(), 'att');
        file_put_contents($copy, str_pad("%PDF-1.4\n", $kilobytes * 1024, 'A'));

        return new UploadedFile($copy, $name, null, null, true);
    }
}
