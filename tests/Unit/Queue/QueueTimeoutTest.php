<?php

declare(strict_types=1);

namespace Tests\Unit\Queue;

use App\Jobs\GenerateRecurringInvoiceJob;
use App\Jobs\GenerateScheduledJobsJob;
use App\Jobs\RunTaskWithAi;
use App\Notifications\InvoiceIssued;
use Illuminate\Queue\Attributes\FailOnTimeout;
use Illuminate\Queue\Attributes\Timeout;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class QueueTimeoutTest extends TestCase
{
    /**
     * @return array<string, array{class-string}>
     */
    public static function queuedClasses(): array
    {
        return [
            'InvoiceIssued' => [InvoiceIssued::class],
            'GenerateRecurringInvoiceJob' => [GenerateRecurringInvoiceJob::class],
            'GenerateScheduledJobsJob' => [GenerateScheduledJobsJob::class],
            'RunTaskWithAi' => [RunTaskWithAi::class],
        ];
    }

    /**
     * @param  class-string  $class
     */
    #[DataProvider('queuedClasses')]
    public function test_timeout_stays_under_managed_queue_limit(string $class): void
    {
        $reflection = new ReflectionClass($class);
        $attributes = $reflection->getAttributes(Timeout::class);

        $this->assertCount(1, $attributes);
        $this->assertLessThan(90, $attributes[0]->newInstance()->timeout);
        $this->assertNotEmpty($reflection->getAttributes(FailOnTimeout::class));
    }
}
