<?php

declare(strict_types=1);

namespace Tests\Feature\Schedule;

use App\Enums\JobStatusEnum;
use Tests\TestCase;

final class JobStatusEnumTest extends TestCase
{
    // -------------------------------------------------------------------------
    // allowedTransitions / canTransitionTo matrix
    // -------------------------------------------------------------------------

    public function test_unassigned_can_transition_to_planned(): void
    {
        $this->assertTrue(JobStatusEnum::Unassigned->canTransitionTo(JobStatusEnum::Planned));
    }

    public function test_unassigned_can_transition_to_cancelled(): void
    {
        $this->assertTrue(JobStatusEnum::Unassigned->canTransitionTo(JobStatusEnum::Cancelled));
    }

    public function test_unassigned_cannot_transition_to_in_progress(): void
    {
        $this->assertFalse(JobStatusEnum::Unassigned->canTransitionTo(JobStatusEnum::InProgress));
    }

    public function test_unassigned_cannot_transition_to_completed(): void
    {
        $this->assertFalse(JobStatusEnum::Unassigned->canTransitionTo(JobStatusEnum::Completed));
    }

    public function test_planned_can_transition_to_in_progress(): void
    {
        $this->assertTrue(JobStatusEnum::Planned->canTransitionTo(JobStatusEnum::InProgress));
    }

    public function test_planned_can_transition_to_cancelled(): void
    {
        $this->assertTrue(JobStatusEnum::Planned->canTransitionTo(JobStatusEnum::Cancelled));
    }

    public function test_planned_cannot_transition_to_completed(): void
    {
        $this->assertFalse(JobStatusEnum::Planned->canTransitionTo(JobStatusEnum::Completed));
    }

    public function test_in_progress_can_transition_to_completed(): void
    {
        $this->assertTrue(JobStatusEnum::InProgress->canTransitionTo(JobStatusEnum::Completed));
    }

    public function test_in_progress_can_transition_to_unapproved(): void
    {
        $this->assertTrue(JobStatusEnum::InProgress->canTransitionTo(JobStatusEnum::Unapproved));
    }

    public function test_in_progress_cannot_transition_to_cancelled(): void
    {
        $this->assertFalse(JobStatusEnum::InProgress->canTransitionTo(JobStatusEnum::Cancelled));
    }

    public function test_completed_has_no_allowed_transitions(): void
    {
        $this->assertEmpty(JobStatusEnum::Completed->allowedTransitions());
    }

    public function test_unapproved_has_no_allowed_transitions(): void
    {
        $this->assertEmpty(JobStatusEnum::Unapproved->allowedTransitions());
    }

    public function test_cancelled_has_no_allowed_transitions(): void
    {
        $this->assertEmpty(JobStatusEnum::Cancelled->allowedTransitions());
    }

    // -------------------------------------------------------------------------
    // isEditable
    // -------------------------------------------------------------------------

    public function test_unassigned_is_editable(): void
    {
        $this->assertTrue(JobStatusEnum::Unassigned->isEditable());
    }

    public function test_planned_is_editable(): void
    {
        $this->assertTrue(JobStatusEnum::Planned->isEditable());
    }

    public function test_in_progress_is_editable(): void
    {
        $this->assertTrue(JobStatusEnum::InProgress->isEditable());
    }

    public function test_completed_is_not_editable(): void
    {
        $this->assertFalse(JobStatusEnum::Completed->isEditable());
    }

    public function test_cancelled_is_not_editable(): void
    {
        $this->assertFalse(JobStatusEnum::Cancelled->isEditable());
    }

    // -------------------------------------------------------------------------
    // isTerminal
    // -------------------------------------------------------------------------

    public function test_completed_is_terminal(): void
    {
        $this->assertTrue(JobStatusEnum::Completed->isTerminal());
    }

    public function test_unapproved_is_terminal(): void
    {
        $this->assertTrue(JobStatusEnum::Unapproved->isTerminal());
    }

    public function test_cancelled_is_terminal(): void
    {
        $this->assertTrue(JobStatusEnum::Cancelled->isTerminal());
    }

    public function test_planned_is_not_terminal(): void
    {
        $this->assertFalse(JobStatusEnum::Planned->isTerminal());
    }

    public function test_unassigned_is_not_terminal(): void
    {
        $this->assertFalse(JobStatusEnum::Unassigned->isTerminal());
    }
}
