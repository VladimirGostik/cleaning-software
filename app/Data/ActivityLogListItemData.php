<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\Activity;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class ActivityLogListItemData extends Data
{
    public function __construct(
        public readonly int $id,
        public readonly ?string $log_name,
        public readonly string $description,
        public readonly ?string $subject_type,
        public readonly ?string $subject_id,
        public readonly ?string $subject_label,
        public readonly ?string $event,
        public readonly ?string $causer_name,
        public readonly ?string $causer_email,
        public readonly string $created_at,
        public readonly ActivityRevertData $revert,
    ) {}

    public static function fromModel(Activity $activity, ActivityRevertData $revert, ?string $subjectLabel): self
    {
        $causer = $activity->causerUser();

        return new self(
            id: $activity->id,
            log_name: $activity->log_name,
            description: $activity->description,
            subject_type: $activity->subject_type ? class_basename($activity->subject_type) : null,
            subject_id: $activity->subject_id ? (string) $activity->subject_id : null,
            subject_label: $subjectLabel,
            event: $activity->event,
            causer_name: $causer?->name,
            causer_email: $causer?->email,
            created_at: $activity->created_at?->toIso8601String() ?? '',
            revert: $revert,
        );
    }
}
