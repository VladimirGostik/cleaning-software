<?php

declare(strict_types=1);

namespace App\Data\Ai;

use App\Enums\ConfirmationChannelEnum;
use App\Enums\PendingActionEnum;
use App\Enums\PendingActionStatusEnum;
use App\Models\PendingAction;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class PendingActionData extends Data
{
    public function __construct(
        public readonly string $id,
        public readonly PendingActionEnum $action,
        public readonly string $preview,
        public readonly string $tenant_id,
        public readonly string $tenant_name,
        public readonly string $subject_label,
        public readonly ?string $customer,
        public readonly ?string $total,
        public readonly ?string $email,
        public readonly string $expires_at,
        public readonly bool $confirm_in_app_only,
        public readonly PendingActionStatusEnum $status,
    ) {}

    public static function fromModel(PendingAction $action): self
    {
        $action->loadMissing('tenant:id,name');

        return new self(
            id: $action->id,
            action: $action->action,
            preview: $action->preview,
            tenant_id: $action->tenant_id,
            tenant_name: $action->tenant->name,
            subject_label: $action->subject_label,
            customer: $action->customer,
            total: $action->total,
            email: $action->email,
            expires_at: $action->expires_at->toIso8601String(),
            confirm_in_app_only: $action->allowedConfirmationChannels() === [ConfirmationChannelEnum::App],
            status: $action->isPending() && $action->isExpired() ? PendingActionStatusEnum::Expired : $action->status,
        );
    }
}
