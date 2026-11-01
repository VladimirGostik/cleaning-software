<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Enums\ActionChannelEnum;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Ai\ToolAudit;
use App\Services\Ai\ToolContext;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Support\ValidationMessages;
use LogicException;
use ReflectionMethod;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

abstract class CleanMasterTool extends Tool
{
    /** @var array<string, mixed> */
    protected array $auditExtra = [];

    public function __construct(protected ToolContext $context, protected ToolAudit $audit) {}

    private ActionChannelEnum $actingChannel = ActionChannelEnum::Mcp;

    public function asChannel(ActionChannelEnum $channel): static
    {
        $clone = clone $this;
        $clone->actingChannel = $channel;

        return $clone;
    }

    protected function channel(): ActionChannelEnum
    {
        return $this->actingChannel;
    }

    protected function assertCompanyAllowed(User $user, string $companyId): void
    {
        Gate::forUser($user)->authorize('actIn', [Tenant::class, $companyId]);
    }

    final public function invoke(Request $request): Response
    {
        $response = app()->call($this->entrypoint(), ['request' => $request]);

        return $response instanceof Response ? $response : throw new LogicException('Tool handle() must return a Response.');
    }

    private function entrypoint(): Closure
    {
        return (new ReflectionMethod($this, 'handle'))->getClosure($this);
    }

    protected function actor(Request $request): User
    {
        $user = $request->user();

        if (! $user instanceof User) {
            throw new AuthenticationException;
        }

        return $user;
    }

    /**
     * @param  Closure(User): Response  $callback
     */
    protected function guard(Request $request, ?string $companyId, Closure $callback, bool $audit = true): Response
    {
        $user = $this->actor($request);
        $this->auditExtra = [];

        try {
            $response = $callback($user);
        } catch (AuthorizationException) {
            return $this->failed($user, $request, $companyId, $audit, __('app.tool_not_allowed'));
        } catch (ValidationException $exception) {
            return $this->failed($user, $request, $companyId, $audit, ValidationMessages::from($exception));
        } catch (ModelNotFoundException) {
            return $this->failed($user, $request, $companyId, $audit, __('app.tool_not_found'));
        } catch (HttpExceptionInterface $exception) {
            if ($exception->getStatusCode() >= 500) {
                $this->record($user, $request, $companyId, $audit, 'error', ['reason' => class_basename($exception)]);

                throw $exception;
            }

            return $this->failed($user, $request, $companyId, $audit, $exception->getMessage() !== '' ? $exception->getMessage() : __('app.tool_not_allowed'));
        } catch (Throwable $exception) {
            $this->record($user, $request, $companyId, $audit, 'error', ['reason' => class_basename($exception)]);

            throw $exception;
        }

        $this->record($user, $request, $companyId, $audit, $response->isError() ? 'error' : 'ok');

        return $response;
    }

    protected function json(mixed $data): Response
    {
        return Response::text(json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    private function failed(User $user, Request $request, ?string $companyId, bool $audit, string $message): Response
    {
        $this->record($user, $request, $companyId, $audit, 'error', ['reason' => Str::limit($message, 200)]);

        return Response::error($message);
    }

    /** @param  array<string, mixed>  $extra */
    private function record(User $user, Request $request, ?string $companyId, bool $audit, string $outcome, array $extra = []): void
    {
        if (! $audit) {
            return;
        }

        $tenantId = $companyId !== null && Str::isUuid($companyId) && $user->hasActiveMembership($companyId) ? $companyId : null;

        $this->audit->record($user, $this->name(), $tenantId, $this->channel(), $request->all(), $outcome, [...$this->auditExtra, ...$extra]);
    }
}
