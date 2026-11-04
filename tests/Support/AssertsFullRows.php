<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Models\Activity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

trait AssertsFullRows
{
    /** @param  array<string, mixed>  $expected  every column except id and timestamps, so a new column fails the test */
    protected function assertFullRow(Model $model, array $expected): void
    {
        $row = (array) DB::table($model->getTable())->where('id', $model->getKey())->first();
        unset($row['id'], $row['created_at'], $row['updated_at']);

        $actualKeys = array_keys($row);
        $expectedKeys = array_keys($expected);
        sort($actualKeys);
        sort($expectedKeys);
        $this->assertSame($expectedKeys, $actualKeys, 'column set of '.$model->getTable());

        foreach ($expected as $column => $value) {
            $actual = $row[$column];

            if (is_array($value)) {
                $this->assertEquals($value, is_string($actual) ? json_decode($actual, true) : $actual, $model->getTable().'.'.$column);

                continue;
            }

            $this->assertSame($value, $actual, $model->getTable().'.'.$column);
        }
    }

    /** @return array<string, mixed> */
    protected function createdActivityAttributes(Model $model): array
    {
        $activity = Activity::query()
            ->where('subject_type', $model->getMorphClass())
            ->where('subject_id', $model->getKey())
            ->where('event', 'created')
            ->sole();

        $changes = json_decode(json_encode($activity->attribute_changes, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsArray($changes);
        $this->assertArrayHasKey('attributes', $changes);
        $this->assertIsArray($changes['attributes']);

        /** @var array<string, mixed> $attributes */
        $attributes = $changes['attributes'];

        return $attributes;
    }
}
