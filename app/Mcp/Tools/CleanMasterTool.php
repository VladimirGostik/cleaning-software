<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Enums\ActionChannelEnum;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Ai\Guard\PromptGuard;
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
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use Laravel\Mcp\Support\ValidationMessages;
use LogicException;
use ReflectionClass;
use ReflectionMethod;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

abstract class CleanMasterTool extends Tool
{
    /** @var array<string, mixed> */
    protected array $auditExtra = [];

    public function __construct(protected ToolContext $context, protected ToolAudit $audit, protected PromptGuard $promptGuard) {}

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

    public function isReadOnly(): bool
    {
        $attribute = (new ReflectionClass($this))->getAttributes(IsReadOnly::class)[0] ?? null;

        return $attribute?->newInstance()->value === true;
    }

    /** What the injection screen reads: tools that add their own server-authored text exclude it here. */
    public function screenedOutput(string $text): string
    {
        return $text;
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    protected function scannedArguments(array $arguments): array
    {
        return $arguments;
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    protected function auditedArguments(array $arguments): array
    {
        return $arguments;
    }

    protected function assertCompanyAllowed(User $user, string $companyId): void
    {
        Gate::forUser($user)->authorize($this->channel() === ActionChannelEnum::Assistant ? 'chatIn' : 'actIn', [Tenant::class, $companyId]);
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

        if ($this->channel() === ActionChannelEnum::Mcp && $this->refusedByGuard($user, $request, $companyId)) {
            return $this->failed($user, $request, $companyId, $audit, __('app.mcp_guard_blocked'));
        }

        try {
            $response = $callback($user);

            if ($this->channel() === ActionChannelEnum::Mcp) {
                $response = $this->screenResponse($user, $companyId, $response);
            }
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

    private function refusedByGuard(User $user, Request $request, ?string $companyId): bool
    {
        if ($this->isReadOnly()) {
            return false;
        }

        $verdict = $this->promptGuard->scanValues($this->scannedArguments($request->all()));

        if ($verdict->flagged) {
            $this->promptGuard->restrictMcp($user);
            $this->promptGuard->record($user, 'arguments:'.$this->name(), $this->auditTenant($user, $companyId), ActionChannelEnum::Mcp, $verdict);
        }

        return $this->promptGuard->isMcpRestricted($user);
    }

    private function screenResponse(User $user, ?string $companyId, Response $response): Response
    {
        $text = (string) $response->content();
        $verdict = $this->promptGuard->scanOutput($this->screenedOutput($text));
        $clean = PromptGuard::stripInvisible($text);

        if ($verdict->flagged) {
            $this->promptGuard->restrictMcp($user);
            $this->promptGuard->record($user, 'output:'.$this->name(), $this->auditTenant($user, $companyId), ActionChannelEnum::Mcp, $verdict);

            $clean = (string) json_encode(
                ['_security_notice' => __('app.mcp_guard_notice'), 'data' => json_decode($clean) ?? $clean],
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
            );
        } elseif ($clean === $text) {
            return $response;
        }

        return $response->isError() ? Response::error($clean) : Response::text($clean);
    }

    private function auditTenant(User $user, ?string $companyId): ?string
    {
        return $companyId !== null && Str::isUuid($companyId) && $user->hasActiveMembership($companyId) ? $companyId : null;
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

        $this->audit->record($user, $this->name(), $this->auditTenant($user, $companyId), $this->channel(), $this->auditedArguments($request->all()), $outcome, [...$this->auditExtra, ...$extra]);
    }
}
