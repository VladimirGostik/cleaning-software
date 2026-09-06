<?php

declare(strict_types=1);

namespace Tests\Feature\Schedule;

use App\Enums\JobStatusEnum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class JobStatusEnumTest extends TestCase
{
    /** @return array<string, array{JobStatusEnum, JobStatusEnum, bool}> */
    public static function transitionProvider(): array
    {
        return [
            'unassigned to planned' => [JobStatusEnum::Unassigned, JobStatusEnum::Planned, true],
            'unassigned to cancelled' => [JobStatusEnum::Unassigned, JobStatusEnum::Cancelled, true],
            'unassigned to in_progress' => [JobStatusEnum::Unassigned, JobStatusEnum::InProgress, false],
            'unassigned to completed' => [JobStatusEnum::Unassigned, JobStatusEnum::Completed, false],
            'planned to in_progress' => [JobStatusEnum::Planned, JobStatusEnum::InProgress, true],
            'planned to cancelled' => [JobStatusEnum::Planned, JobStatusEnum::Cancelled, true],
            'planned to completed' => [JobStatusEnum::Planned, JobStatusEnum::Completed, false],
            'planned to unassigned' => [JobStatusEnum::Planned, JobStatusEnum::Unassigned, false],
            'in_progress to completed' => [JobStatusEnum::InProgress, JobStatusEnum::Completed, true],
            'in_progress to unapproved' => [JobStatusEnum::InProgress, JobStatusEnum::Unapproved, true],
            'in_progress to cancelled' => [JobStatusEnum::InProgress, JobStatusEnum::Cancelled, false],
            'in_progress to planned' => [JobStatusEnum::InProgress, JobStatusEnum::Planned, false],
            'completed to anything' => [JobStatusEnum::Completed, JobStatusEnum::Planned, false],
            'unapproved to anything' => [JobStatusEnum::Unapproved, JobStatusEnum::Planned, false],
            'cancelled to anything' => [JobStatusEnum::Cancelled, JobStatusEnum::Planned, false],
        ];
    }

    #[DataProvider('transitionProvider')]
    public function test_can_transition_to(JobStatusEnum $from, JobStatusEnum $to, bool $expected): void
    {
        $this->assertSame($expected, $from->canTransitionTo($to));
    }

    /** @return array<string, array{JobStatusEnum, bool}> */
    public static function editableProvider(): array
    {
        return [
            'unassigned' => [JobStatusEnum::Unassigned, true],
            'planned' => [JobStatusEnum::Planned, true],
            'in_progress' => [JobStatusEnum::InProgress, true],
            'unapproved' => [JobStatusEnum::Unapproved, true],
            'completed' => [JobStatusEnum::Completed, false],
            'cancelled' => [JobStatusEnum::Cancelled, false],
        ];
    }

    #[DataProvider('editableProvider')]
    public function test_is_editable(JobStatusEnum $status, bool $expected): void
    {
        $this->assertSame($expected, $status->isEditable());
    }

    /** @return array<string, array{JobStatusEnum, bool}> */
    public static function terminalProvider(): array
    {
        return [
            'unassigned' => [JobStatusEnum::Unassigned, false],
            'planned' => [JobStatusEnum::Planned, false],
            'in_progress' => [JobStatusEnum::InProgress, false],
            'completed' => [JobStatusEnum::Completed, true],
            'unapproved' => [JobStatusEnum::Unapproved, true],
            'cancelled' => [JobStatusEnum::Cancelled, true],
        ];
    }

    #[DataProvider('terminalProvider')]
    public function test_is_terminal(JobStatusEnum $status, bool $expected): void
    {
        $this->assertSame($expected, $status->isTerminal());
    }

    public function test_allowed_transitions_lists_only_permitted_targets(): void
    {
        $this->assertSame([JobStatusEnum::Planned, JobStatusEnum::Cancelled], JobStatusEnum::Unassigned->allowedTransitions());
        $this->assertSame([], JobStatusEnum::Completed->allowedTransitions());
    }
}
