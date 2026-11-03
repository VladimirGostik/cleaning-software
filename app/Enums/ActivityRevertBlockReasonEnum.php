<?php

declare(strict_types=1);

namespace App\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum ActivityRevertBlockReasonEnum: string
{
    case UnsupportedOperation = 'unsupported_operation';
    case UnsupportedModel = 'unsupported_model';
    case LifecycleField = 'lifecycle_field';
    case NoCompany = 'no_company';
    case Forbidden = 'forbidden';
    case SubjectMissing = 'subject_missing';
    case SubjectDeleted = 'subject_deleted';
    case SubjectNotDeleted = 'subject_not_deleted';
    case ParentDeleted = 'parent_deleted';
    case AlreadyReverted = 'already_reverted';
    case Conflict = 'conflict';
    case HasLaterChanges = 'has_later_changes';
    case NotDraft = 'not_draft';
    case PrimaryContact = 'primary_contact';
    case NoChanges = 'no_changes';
    case UniqueConflict = 'unique_conflict';
    case BatchMemberBlocked = 'batch_member_blocked';

    public function label(): string
    {
        return __('app.audit_revert_reason_'.$this->value);
    }
}
