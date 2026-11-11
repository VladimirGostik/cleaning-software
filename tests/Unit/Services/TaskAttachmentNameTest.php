<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Support\MediaFiles;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TaskAttachmentNameTest extends TestCase
{
    /** @return array<string, array{string, string}> */
    public static function vectors(): array
    {
        return [
            'unchanged' => ['faktúra 2026.pdf', 'faktúra 2026.pdf'],
            'windows path' => ['C:\Users\x\foto.jpg', 'foto.jpg'],
            'unix path' => ['a/b/foto.jpg', 'foto.jpg'],
            'invisible removed' => ["a\u{200B}b.pdf", 'ab.pdf'],
            'control removed' => ["\u{0007}x\n.jpg", 'x.jpg'],
            'separators trimmed' => ["  scan.pdf\u{00A0}", 'scan.pdf'],
            'empty' => ['', 'file'],
            'long with extension' => [str_repeat('a', 130).'.pdf', str_repeat('a', 116).'.pdf'],
            'long without extension' => [str_repeat('a', 130), str_repeat('a', 120)],
        ];
    }

    #[DataProvider('vectors')]
    public function test_clean_name_follows_the_shared_vectors(string $input, string $expected): void
    {
        $this->assertSame($expected, MediaFiles::cleanName($input));
    }

    public function test_long_name_is_counted_in_code_points(): void
    {
        $this->assertSame(str_repeat('č', 120), MediaFiles::cleanName(str_repeat('č', 130)));
    }

    public function test_name_made_only_of_invisible_characters_becomes_file(): void
    {
        $this->assertSame('file', MediaFiles::cleanName("\u{200B}\u{FEFF}\n"));
    }
}
