<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Enums\PendingActionEnum;
use App\Models\Task;
use App\Models\User;
use App\Services\Ai\ActionConfirmationService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[Name('prepare_action')]
#[Description('Step one of an irreversible action (issue/send invoice, send quote, sign contract, cancel invoice, delete invoice draft/quote/task/expense/contact). Nothing happens yet: it returns a preview and a one-time token. Show the preview verbatim to the user, and only after their explicit yes call confirm_action. Actions marked confirm_in_app_only must be confirmed by the user in the app.')]
#[IsDestructive]
final class PrepareActionTool extends TenantTool
{
    protected function rules(): array
    {
        return [
            'action' => ['required', Rule::enum(PendingActionEnum::class)],
            'subject_id' => ['required', 'uuid'],
            'number' => ['sometimes', 'nullable', 'string', 'max:50'],
        ];
    }

    public function screenedOutput(string $text): string
    {
        $data = json_decode($text, true);

        return is_array($data) ? (string) json_encode($data['pending_action'] ?? $data, JSON_UNESCAPED_UNICODE) : $text;
    }

    public function execute(Request $request, User $user, string $companyId, ActionConfirmationService $actions): Response
    {
        $action = PendingActionEnum::from($this->id($request, 'action'));
        $subject = $action->subjectClass()::query()->findOrFail($this->id($request, 'subject_id'));

        if ($subject instanceof Task && $subject->isGlobal()) {
            throw ValidationException::withMessages(['subject_id' => [__('app.pending_action_global_task_use_direct')]]);
        }

        $number = $request->get('number');
        $payload = $action === PendingActionEnum::IssueInvoice && is_string($number) && $number !== '' ? ['number' => $number] : [];

        $prepared = $actions->prepare($user, $companyId, $action, $subject, $payload, $this->channel());

        return $this->json([
            'pending_action' => $prepared->pending_action,
            'token' => $prepared->token,
            'next_step' => $prepared->pending_action->confirm_in_app_only ? __('app.prepare_next_app') : __('app.prepare_next_chat'),
        ]);
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        return [
            ...$this->companyProperty($schema),
            'action' => $schema->string()->enum(array_map(fn (PendingActionEnum $case): string => $case->value, PendingActionEnum::cases()))->description('What to do.')->required(),
            'subject_id' => $schema->string()->format('uuid')->description('Id of the invoice, quote, contract, task, expense or contact the action applies to.')->required(),
            'number' => $schema->string()->max(50)->nullable()->description('issue_invoice only: explicit invoice number instead of the next one in the sequence.'),
        ];
    }
}
