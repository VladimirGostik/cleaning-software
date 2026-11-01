<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\HasUuids;
use App\Enums\ActionChannelEnum;
use App\Enums\ConfirmationChannelEnum;
use App\Enums\PendingActionEnum;
use App\Enums\PendingActionStatusEnum;
use Database\Factories\PendingActionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $user_id
 * @property string|null $task_run_id
 * @property PendingActionEnum $action
 * @property string $subject_type
 * @property string $subject_id
 * @property array<string, mixed> $payload
 * @property string $payload_hash
 * @property string|null $token_hash
 * @property string $subject_hash
 * @property string|null $customer
 * @property string|null $total
 * @property string|null $email
 * @property string $preview
 * @property string $subject_label
 * @property ActionChannelEnum $channel
 * @property PendingActionStatusEnum $status
 * @property Carbon $expires_at
 * @property Carbon|null $executed_at
 * @property ConfirmationChannelEnum|null $confirmed_via
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Tenant $tenant
 */
// No tenant scope on purpose: confirm acts on a row of a firm other than the active one.
#[Fillable([
    'tenant_id', 'user_id', 'action', 'subject_type', 'subject_id', 'payload', 'payload_hash', 'subject_hash', 'customer', 'total', 'email', 'token_hash',
    'preview', 'subject_label', 'channel', 'status', 'expires_at', 'executed_at', 'confirmed_via',
])]
final class PendingAction extends Model
{
    /** @use HasFactory<PendingActionFactory> */
    use HasFactory, HasUuids;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'action' => PendingActionEnum::class,
            'channel' => ActionChannelEnum::class,
            'status' => PendingActionStatusEnum::class,
            'confirmed_via' => ConfirmationChannelEnum::class,
            'payload' => 'array',
            'expires_at' => 'datetime',
            'executed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Assistant and task-run rows are always confirmed in the app (hard rule); MCP rows follow `ai.confirm_channels`, fail-closed to app-only.
     *
     * @return list<ConfirmationChannelEnum>
     */
    public function allowedConfirmationChannels(): array
    {
        if ($this->channel !== ActionChannelEnum::Mcp) {
            return [ConfirmationChannelEnum::App];
        }

        /** @var list<string>|null $configured */
        $configured = config('ai.confirm_channels.'.$this->action->value);

        if (! is_array($configured)) {
            return [ConfirmationChannelEnum::App];
        }

        return array_values(array_filter(array_map(ConfirmationChannelEnum::tryFrom(...), $configured)));
    }

    public function isPending(): bool
    {
        return $this->status === PendingActionStatusEnum::Pending;
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }
}
