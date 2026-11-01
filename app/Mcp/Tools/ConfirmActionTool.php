<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Enums\ConfirmationChannelEnum;
use App\Models\User;
use App\Services\Ai\ActionConfirmationService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[Name('confirm_action')]
#[Description('Step two: executes a prepared action with its one-time token. Call it only after the user explicitly said yes to the preview in this conversation. Refused for actions that must be confirmed in the app.')]
#[IsDestructive]
final class ConfirmActionTool extends CleanMasterTool
{
    public function handle(Request $request, ActionConfirmationService $actions): Response
    {
        return $this->guard($request, null, function (User $user) use ($request, $actions): Response {
            /** @var array{token: string} $arguments */
            $arguments = $request->validate(['token' => ['required', 'string', 'min:20', 'max:100']]);

            try {
                $pending = $actions->resolveToken($user, $arguments['token']);
            } catch (ValidationException $exception) {
                $this->audit->record($user, 'confirm', null, $this->channel(), [], 'error', ['reason' => 'token']);

                throw $exception;
            }

            return $this->json($actions->execute($user, $pending, ConfirmationChannelEnum::Chat));
        }, audit: false);
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'token' => $schema->string()->description('The token returned by prepare_action.')->required(),
        ];
    }
}
