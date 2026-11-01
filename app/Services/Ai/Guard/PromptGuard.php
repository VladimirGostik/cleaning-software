<?php

declare(strict_types=1);

namespace App\Services\Ai\Guard;

use App\Enums\ActionChannelEnum;
use App\Models\Activity;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

final readonly class PromptGuard
{
    private const int MAX_EXCERPT = 200;

    private const string SKIPPED_KEYS = '/token|password|secret|receipt/i';

    public function __construct(private PromptInjectionDetector $detector) {}

    public static function stripInvisible(string $text): string
    {
        return (string) preg_replace('/[\x{00AD}\x{034F}\x{115F}\x{1160}\x{180E}\x{200B}\x{200C}\x{200E}\x{200F}\x{202A}-\x{202E}\x{2060}-\x{2069}\x{3164}\x{FE00}-\x{FE0E}\x{FEFF}\x{FFA0}\x{E0000}-\x{E007F}\x{E0100}-\x{E01EF}]/u', '', mb_scrub($text, 'UTF-8'));
    }

    public function scan(string $text): InjectionVerdict
    {
        return $this->detector->scan($text);
    }

    /** @param  array<array-key, mixed>  $values */
    public function scanValues(array $values): InjectionVerdict
    {
        $strings = [];

        array_walk_recursive($values, static function (mixed $value, int|string $key) use (&$strings): void {
            if (is_string($value) && (! is_string($key) || preg_match(self::SKIPPED_KEYS, $key) !== 1)) {
                $strings[] = $value;
            }
        });

        $flagged = [];

        // Scored per value: weak hints in two unrelated records must not add up to an alarm.
        foreach (array_unique($strings) as $string) {
            $verdict = $this->scan($string);

            if ($verdict->flagged) {
                $flagged[] = $verdict;
            }
        }

        if ($flagged === []) {
            return InjectionVerdict::clean();
        }

        return new InjectionVerdict(
            true,
            max(array_map(static fn (InjectionVerdict $verdict): int => $verdict->score, $flagged)),
            array_values(array_unique(array_merge(...array_map(static fn (InjectionVerdict $verdict): array => $verdict->rules, $flagged)))),
            $flagged[0]->evidence,
        );
    }

    public function scanOutput(string $text): InjectionVerdict
    {
        $decoded = json_decode($text, true);

        return is_array($decoded) ? $this->scanValues($decoded) : $this->scan($text);
    }

    public function record(?User $user, string $source, ?string $tenantId, ?ActionChannelEnum $channel, InjectionVerdict $verdict): void
    {
        $logger = activity('ai');
        $causer = $user ?? Auth::user();

        if ($causer instanceof User) {
            $logger->causedBy($causer);
        }

        $logger
            ->withProperties([
                'channel' => $channel?->value,
                'source' => $source,
                'company_id' => $tenantId,
                'rules' => $verdict->rules,
                'score' => $verdict->score,
                'excerpt' => $this->maskedExcerpt($verdict),
            ])
            ->tap(function (Activity $activity) use ($tenantId): void {
                $activity->tenant_id = $tenantId;
                $activity->keepNullTenant = $tenantId === null;
            })
            ->log('guard.prompt_injection');
    }

    private function maskedExcerpt(InjectionVerdict $verdict): string
    {
        return Str::limit((string) preg_replace(
            ['/[^\s"]+@[^\s"]+\.\w{2,}/u', '/\b[a-z]{2}\d{2}[a-z0-9 ]{11,30}\b/iu', '/\d{4,}/u'],
            '***',
            $verdict->evidence ?? '',
        ), self::MAX_EXCERPT);
    }

    public function restrictMcp(User $user): void
    {
        Cache::put($this->restrictionKey($user), true, now()->addMinutes(config()->integer('ai.guard.mcp_restriction_minutes', 15)));
    }

    public function isMcpRestricted(User $user): bool
    {
        return Cache::has($this->restrictionKey($user));
    }

    private function restrictionKey(User $user): string
    {
        return 'ai-guard:mcp-restricted:'.$user->id;
    }

    /**
     * @return array{text: string, withheld: bool}
     */
    public function filterReply(string $reply, string $nonce, bool $restricted): array
    {
        $plain = trim(strip_tags($reply));

        if (str_contains($reply, $nonce) || str_contains($plain, $nonce)) {
            return ['text' => __('app.assistant_guard_withheld'), 'withheld' => true];
        }

        // Tags are stripped first: `!<b></b>[x](url)` would otherwise become an image only after filtering.
        $text = (string) preg_replace('/!\[[^\]]*\](?:\([^)]*\)|\[[^\]]*\])/u', '', $plain);
        $text = (string) preg_replace(
            '/sk-ant-[\w\-]+|\b\d+\|[A-Za-z0-9]{30,}\b|\bBearer\s+[\w.\-]{20,}|-----BEGIN [A-Z ]+-----/u',
            __('app.assistant_guard_redacted'),
            $text,
        );

        if ($restricted) {
            $text = (string) preg_replace('~\b(?:https?://|www\.)[^\s<>"\']+~iu', __('app.assistant_guard_link_removed'), $text);
        }

        return ['text' => $text, 'withheld' => false];
    }
}
