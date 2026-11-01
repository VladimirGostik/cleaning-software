<?php

declare(strict_types=1);

namespace Tests\Feature\Documents;

use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Mockery\Expectation;
use Mockery\MockInterface;
use RuntimeException;
use Tests\TestCase;

final class VerifyDocumentsDiskCommandTest extends TestCase
{
    public function test_a_working_disk_passes_every_step_and_leaves_no_probe_behind(): void
    {
        Storage::fake('docs');
        config(['documents.disk' => 'docs']);

        $this->artisanCommand('documents:verify-disk')
            ->expectsOutputToContain('[pass] write')
            ->expectsOutputToContain('[pass] exists')
            ->expectsOutputToContain('[pass] read back')
            ->expectsOutputToContain('[pass] delete')
            ->expectsOutputToContain('Documents disk verified.')
            ->assertSuccessful();

        $this->assertSame([], Storage::disk('docs')->allFiles());
    }

    public function test_a_write_failure_fails_but_still_cleans_up(): void
    {
        config(['documents.disk' => 'docs']);
        /** @var FilesystemAdapter&MockInterface $disk */
        $disk = Mockery::mock(FilesystemAdapter::class);
        /** @var Expectation $put */
        $put = $disk->shouldReceive('put');
        $put->once()->andThrow(new RuntimeException('Access Denied'));
        $disk->allows(['delete' => true, 'exists' => false]);
        Storage::swap(new class($disk)
        {
            public function __construct(private readonly FilesystemAdapter $disk) {}

            public function disk(): FilesystemAdapter
            {
                return $this->disk;
            }
        });

        $this->artisanCommand('documents:verify-disk')
            ->expectsOutputToContain('[FAIL] write: RuntimeException - Access Denied')
            ->expectsOutputToContain('[pass] delete')
            ->expectsOutputToContain('Documents disk verification FAILED.')
            ->assertFailed();
    }

    public function test_failure_output_never_contains_a_presigned_signature(): void
    {
        config(['documents.disk' => 'docs']);
        /** @var FilesystemAdapter&MockInterface $disk */
        $disk = Mockery::mock(FilesystemAdapter::class);
        /** @var Expectation $put */
        $put = $disk->shouldReceive('put');
        $put->once()->andThrow(new RuntimeException('GET https://bucket.s3.example/key?X-Amz-Signature=sig123secret&X-Amz-Credential=cred456 failed; X-Amz-Signature=sig123secret'));
        $disk->allows(['delete' => true, 'exists' => false]);
        Storage::swap(new class($disk)
        {
            public function __construct(private readonly FilesystemAdapter $disk) {}

            public function disk(): FilesystemAdapter
            {
                return $this->disk;
            }
        });

        $this->artisanCommand('documents:verify-disk')
            ->expectsOutputToContain('[FAIL] write')
            ->doesntExpectOutputToContain('sig123secret')
            ->doesntExpectOutputToContain('cred456')
            ->assertFailed();
    }

    public function test_an_unknown_disk_fails_without_a_stack_trace(): void
    {
        config(['documents.disk' => 'does-not-exist']);

        $this->artisanCommand('documents:verify-disk')
            ->expectsOutputToContain('[FAIL] disk')
            ->assertFailed();
    }
}
