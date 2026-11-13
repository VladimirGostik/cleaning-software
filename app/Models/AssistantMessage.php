<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\HasUuids;
use App\Enums\AssistantRoleEnum;
use Database\Factories\AssistantMessageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $assistant_conversation_id
 * @property AssistantRoleEnum $role
 * @property string $content
 * @property list<string>|null $attachment_names
 * @property list<array<string, mixed>>|null $actions
 * @property string|null $pending_action_id
 * @property string|null $security_notice
 * @property string|null $attachment_note
 * @property string|null $client_message_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property AssistantConversation $conversation
 * @property PendingAction|null $pendingAction
 */
#[Fillable(['assistant_conversation_id', 'role', 'content', 'attachment_names', 'actions', 'pending_action_id', 'security_notice', 'attachment_note', 'client_message_id', 'created_at'])]
final class AssistantMessage extends Model
{
    protected $dateFormat = 'Y-m-d H:i:s.u';

    /** @use HasFactory<AssistantMessageFactory> */
    use HasFactory, HasUuids;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'role' => AssistantRoleEnum::class,
            'content' => 'encrypted',
            'attachment_note' => 'encrypted',
            'attachment_names' => 'encrypted:array',
            'actions' => 'encrypted:array',
        ];
    }

    /** @return BelongsTo<AssistantConversation, $this> */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AssistantConversation::class, 'assistant_conversation_id');
    }

    /** @return BelongsTo<PendingAction, $this> */
    public function pendingAction(): BelongsTo
    {
        return $this->belongsTo(PendingAction::class);
    }
}
