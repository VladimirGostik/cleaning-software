<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Models\Activity;
use App\Support\ActivityBatch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

final class ActivityBatchTest extends TestCase
{
    use RefreshDatabase;

    public function test_current_is_null_outside_a_run(): void
    {
        $this->assertNull(ActivityBatch::current());
    }

    public function test_run_exposes_one_uuid_and_returns_the_result(): void
    {
        $result = ActivityBatch::run(fn (): string => (string) ActivityBatch::current());

        $this->assertTrue(Str::isUuid($result));
        $this->assertNull(ActivityBatch::current());
    }

    public function test_nested_run_reuses_the_outer_uuid(): void
    {
        ActivityBatch::run(function (): void {
            $outer = ActivityBatch::current();

            ActivityBatch::run(fn () => $this->assertSame($outer, ActivityBatch::current()));

            $this->assertSame($outer, ActivityBatch::current());
        });

        $this->assertNull(ActivityBatch::current());
    }

    public function test_uuid_resets_after_an_exception(): void
    {
        try {
            ActivityBatch::run(fn () => throw new RuntimeException('boom'));
        } catch (RuntimeException) {
        }

        $this->assertNull(ActivityBatch::current());
    }

    public function test_activities_created_inside_a_run_share_the_uuid_and_others_stay_null(): void
    {
        ActivityBatch::run(function (): void {
            activity()->log('one');
            activity()->log('two');
        });
        activity()->log('outside');

        $inside = Activity::query()->whereIn('description', ['one', 'two'])->pluck('batch_uuid')->unique();

        $this->assertCount(1, $inside);
        $this->assertNotNull($inside->first());
        $this->assertNull(Activity::query()->where('description', 'outside')->value('batch_uuid'));
    }
}
