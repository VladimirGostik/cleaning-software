<?php

declare(strict_types=1);

namespace App\Mcp\Resources;

use App\Enums\ActionChannelEnum;
use App\Models\User;
use App\Services\Ai\ToolAudit;
use App\Services\Ai\ToolContext;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Str;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Contracts\HasUriTemplate;
use Laravel\Mcp\Server\Resource;
use Throwable;

abstract class PdfResource extends Resource implements HasUriTemplate
{
    protected string $mimeType = 'application/pdf';

    public function __construct(protected ToolContext $context, protected ToolAudit $audit) {}

    abstract protected function resourceName(): string;

    /** @return Closure(string): string */
    abstract protected function renderer(User $user): Closure;

    public function handle(Request $request): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            throw new AuthenticationException;
        }

        $company = $request->string('company')->toString();
        $id = $request->string($this->idKey())->toString();
        $tenantId = Str::isUuid($company) && $user->hasActiveMembership($company) ? $company : null;

        try {
            if ($tenantId === null || ! Str::isUuid($id)) {
                throw new AuthorizationException;
            }

            $pdf = $this->context->run($user, $tenantId, fn (): string => ($this->renderer($user))($id));
        } catch (Throwable $exception) {
            $this->audit->record($user, $this->resourceName(), $tenantId, ActionChannelEnum::Mcp, ['uri' => $request->uri()], 'error', ['reason' => class_basename($exception)]);

            return Response::error(match (true) {
                $exception instanceof ModelNotFoundException => __('app.tool_not_found'),
                $exception instanceof AuthorizationException => __('app.tool_not_allowed'),
                default => __('app.tool_failed'),
            });
        }

        $this->audit->record($user, $this->resourceName(), $tenantId, ActionChannelEnum::Mcp, ['uri' => $request->uri()], 'ok');

        return Response::blob($pdf);
    }

    abstract protected function idKey(): string;
}
