<?php

declare(strict_types=1);

namespace App\Services\Ai;

use App\Data\Ai\AssistantActionData;
use App\Data\Ai\AssistantConversationData;
use App\Data\Ai\AssistantConversationItemData;
use App\Data\Ai\AssistantConversationRenameData;
use App\Data\Ai\AssistantMessageData;
use App\Data\Ai\AssistantReplyData;
use App\Data\Ai\AssistantRequestData;
use App\Data\Ai\PendingActionData;
use App\Enums\AssistantRoleEnum;
use App\Models\AssistantConversation;
use App\Models\AssistantMessage;
use App\Models\User;
use App\Services\Ai\Guard\PromptGuard;
use App\Utils\PerPage;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\LaravelData\PaginatedDataCollection;

final readonly class AssistantConversationService
{
    /** @return PaginatedDataCollection<int, AssistantConversationItemData> */
    public function paginate(User $user, Request $request): PaginatedDataCollection
    {
        $request->mergeIfMissing(['per_page' => config()->integer('ai.assistant.history.per_page')]);

        $page = AssistantConversation::query()
            ->where('user_id', $user->id)
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->paginate(PerPage::clamp($request)->integer('per_page'))
            ->through(AssistantConversationItemData::fromModel(...));

        return AssistantConversationItemData::collect($page, PaginatedDataCollection::class);
    }

    public function show(AssistantConversation $conversation): AssistantConversationData
    {
        $conversation->load('messages.pendingAction.tenant');

        return AssistantConversationData::fromModel($conversation);
    }

    public function rename(AssistantConversation $conversation, AssistantConversationRenameData $data): AssistantConversationItemData
    {
        $conversation->update(['title' => $data->title]);

        return AssistantConversationItemData::fromModel($conversation);
    }

    public function delete(AssistantConversation $conversation): void
    {
        DB::transaction(function () use ($conversation): void {
            AssistantMessage::query()->where('assistant_conversation_id', $conversation->id)->delete();
            $conversation->delete();
        });
    }

    public function resolve(User $user, ?string $conversationId): ?AssistantConversation
    {
        if ($conversationId === null) {
            return null;
        }

        $conversation = AssistantConversation::query()->where('user_id', $user->id)->find($conversationId);

        if ($conversation === null) {
            throw ValidationException::withMessages(['conversation_id' => [__('app.assistant_conversation_not_found')]]);
        }

        if ($conversation->messages()->count() >= config()->integer('ai.assistant.history.max_messages') - 1) {
            throw ValidationException::withMessages(['content' => [__('app.assistant_conversation_full')]]);
        }

        return $conversation;
    }

    public function replay(User $user, string $clientMessageId): ?AssistantReplyData
    {
        $stored = AssistantMessage::query()->with('conversation')->where('client_message_id', $clientMessageId)->first();

        if ($stored === null) {
            return null;
        }

        if ($stored->conversation->user_id !== $user->id) {
            throw ValidationException::withMessages(['client_message_id' => [__('app.assistant_client_message_taken')]]);
        }

        $order = AssistantMessage::query()
            ->where('assistant_conversation_id', $stored->assistant_conversation_id)
            ->orderBy('created_at')
            ->orderBy('id')
            ->toBase()
            ->get(['id', 'role']);
        $position = $order->search(static fn (object $row): bool => $row->id === $stored->id);
        $next = $position === false ? null : $order->get($position + 1);
        $answer = $next !== null && $next->role === AssistantRoleEnum::Assistant->value
            ? AssistantMessage::query()->with('pendingAction.tenant')->whereKey($next->id)->first()
            : null;

        if ($answer === null) {
            return null;
        }

        return new AssistantReplyData(
            reply: $answer->content,
            actions: array_map(AssistantActionData::from(...), $answer->actions ?? []),
            pending_action: $answer->pendingAction === null ? null : PendingActionData::fromModel($answer->pendingAction),
            security_notice: $answer->security_notice,
            attachment_note: $answer->attachment_note,
            conversation_id: $stored->conversation->id,
            conversation_title: $stored->conversation->title,
        );
    }

    /** @return list<AssistantMessageData> */
    public function historyFor(?AssistantConversation $conversation, int $currentChars): array
    {
        if ($conversation === null) {
            return [];
        }

        $budget = config()->integer('ai.assistant.max_total_chars') - $currentChars;
        $window = [];

        $recent = $conversation->messages()
            ->reorder()
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(config()->integer('ai.assistant.max_messages') - 1)
            ->get();

        foreach ($recent as $message) {
            $budget -= mb_strlen($message->content);

            if ($budget < 0) {
                break;
            }

            $window[] = new AssistantMessageData($message->role, $message->content, $message->attachment_names ?? []);
        }

        return array_reverse($window);
    }

    public function record(User $user, ?AssistantConversation $conversation, AssistantRequestData $request, AssistantReplyData $reply, CarbonImmutable $receivedAt): AssistantConversation
    {
        return DB::transaction(function () use ($user, $conversation, $request, $reply, $receivedAt): AssistantConversation {
            if ($conversation !== null && ! AssistantConversation::query()->whereKey($conversation->id)->lockForUpdate()->exists()) {
                $conversation = null;
            }

            $names = array_map(static fn (UploadedFile $file): string => PromptGuard::stripInvisible($file->getClientOriginalName()), $request->attachments);
            $conversation ??= AssistantConversation::query()->create([
                'user_id' => $user->id,
                'title' => $this->titleFor($request->text(), $names),
                'last_message_at' => $receivedAt,
            ]);

            AssistantMessage::query()->create([
                'assistant_conversation_id' => $conversation->id,
                'role' => AssistantRoleEnum::User,
                'content' => $request->text(),
                'attachment_names' => $names === [] ? null : $names,
                'client_message_id' => $request->client_message_id,
                'created_at' => $receivedAt,
            ]);

            $answeredAt = CarbonImmutable::now();

            AssistantMessage::query()->create([
                'assistant_conversation_id' => $conversation->id,
                'role' => AssistantRoleEnum::Assistant,
                'content' => $reply->reply,
                'actions' => $reply->actions === [] ? null : array_map(static fn (AssistantActionData $action): array => $action->toArray(), $reply->actions),
                'pending_action_id' => $reply->pending_action?->id,
                'security_notice' => $reply->security_notice,
                'attachment_note' => $reply->attachment_note,
                'created_at' => $answeredAt,
            ]);

            $conversation->update(['last_message_at' => $answeredAt]);

            return $conversation;
        });
    }

    /** @param  list<string>  $fileNames */
    private function titleFor(string $content, array $fileNames): string
    {
        $text = Str::squish(PromptGuard::stripInvisible($content));

        if ($text === '' && $fileNames !== []) {
            $text = Str::squish($fileNames[0]);
        }

        if ($text === '') {
            return __('app.assistant_conversation_untitled');
        }

        return Str::limit($text, config()->integer('ai.assistant.history.title_chars'), '');
    }
}
