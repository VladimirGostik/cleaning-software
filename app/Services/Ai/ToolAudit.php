<?php

declare(strict_types=1);

namespace App\Services\Ai;

use App\Enums\ActionChannelEnum;
use App\Models\Activity;
use App\Models\User;

final readonly class ToolAudit
{
    private const int MAX_STRING = 500;

    private const int MAX_ITEMS = 20;

    private const int MAX_DEPTH = 3;

    private const string REDACTED = '[redacted]';

    /**
     * @param  array<string, mixed>  $arguments
     * @param  array<string, mixed>  $extra
     */
    public function record(
        User $user,
        string $tool,
        ?string $tenantId,
        ActionChannelEnum $channel,
        array $arguments,
        string $outcome,
        array $extra = [],
    ): void {
        activity('ai')
            ->causedBy($user)
            ->withProperties([
                'channel' => $channel->value,
                'tool' => $tool,
                'company_id' => $tenantId,
                'outcome' => $outcome,
                'arguments' => $this->redact($arguments),
                ...$extra,
            ])
            ->tap(function (Activity $activity) use ($tenantId): void {
                $activity->tenant_id = $tenantId;
                $activity->keepNullTenant = $tenantId === null;
            })
            ->log('tool.'.$tool.($outcome === 'ok' ? '' : '.failed'));
    }

    /**
     * @param  array<array-key, mixed>  $values
     * @return array<array-key, mixed>
     */
    public function redact(array $values, int $depth = 0): array
    {
        $redacted = [];
        $count = 0;

        foreach ($values as $key => $value) {
            if (++$count > self::MAX_ITEMS) {
                $redacted['...'] = (count($values) - self::MAX_ITEMS).' more omitted';

                break;
            }

            if (is_string($key) && preg_match('/token|receipt|password|secret/i', $key) === 1) {
                $redacted[$key] = self::REDACTED;

                continue;
            }

            $redacted[$key] = match (true) {
                is_array($value) => $depth >= self::MAX_DEPTH ? '[nested omitted]' : $this->redact($value, $depth + 1),
                is_string($value) && mb_strlen($value) > self::MAX_STRING => mb_substr($value, 0, self::MAX_STRING).'...[truncated]',
                default => $value,
            };
        }

        return $redacted;
    }
}
