<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\MobileTokenPairData;
use App\Enums\MobileSessionRevokeReasonEnum;
use App\Models\MobileSession;
use App\Models\User;
use App\Services\Tenancy\TenantPermissionSnapshot;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\TransientToken;

final readonly class MobileSessionService
{
    public function __construct(
        private DatabaseManager $db,
        private TenantPermissionSnapshot $snapshot,
        private DeviceTokenService $devices,
    ) {}

    public function issue(User $user): MobileTokenPairData
    {
        return $this->db->transaction(function () use ($user): MobileTokenPairData {
            $secret = Str::random(64);

            $session = MobileSession::query()->create([
                'user_id' => $user->id,
                'refresh_hash' => hash('sha256', $secret),
                'expires_at' => $this->newExpiry(),
            ]);

            return new MobileTokenPairData($this->replaceAccessToken($session, $user), $session->id.'.'.$secret);
        });
    }

    /** Null means refused: the controller answers 401. A kill must commit, so nothing throws out of the transaction. */
    public function refresh(string $refreshToken): ?MobileTokenPairData
    {
        $parsed = $this->parse($refreshToken);

        if ($parsed === null) {
            return null;
        }

        [$sessionId, $secret] = $parsed;

        return $this->db->transaction(function () use ($sessionId, $secret): ?MobileTokenPairData {
            $session = MobileSession::query()->with('user')->whereKey($sessionId)->lockForUpdate()->first();

            if ($session === null) {
                return null;
            }

            $presented = hash('sha256', $secret);
            $isCurrent = hash_equals($session->refresh_hash, $presented);
            $isPrevious = ! $isCurrent && $this->matchesPrevious($session, $presented);

            if (! $isCurrent && ! ($isPrevious && $this->previousIsAcceptable($session))) {
                logger()->warning('mobile_session.reuse_detected', ['session_id' => $session->id, 'user_id' => $session->user_id, 'ip' => get_client_ip()]);

                return $this->kill($session, MobileSessionRevokeReasonEnum::ReuseDetected);
            }

            if ($session->expires_at->isPast()) {
                return $this->kill($session, MobileSessionRevokeReasonEnum::Expired);
            }

            if ($this->mobileAccessDenial($session->user) !== null) {
                return $this->kill($session, MobileSessionRevokeReasonEnum::AccessLost);
            }

            $next = hash_hmac('sha256', $session->id.'|'.$secret, config()->string('app.key'));

            $session->refresh_hash = hash('sha256', $next);
            $session->rotation_confirmed_at = null;
            $session->expires_at = $this->newExpiry();

            if ($isCurrent) {
                $session->previous_refresh_hash = $presented;
                $session->rotated_at = now();
            }

            $session->save();

            return new MobileTokenPairData($this->replaceAccessToken($session, $session->user), $session->id.'.'.$next);
        });
    }

    public function revokeByRefreshToken(string $refreshToken): void
    {
        $parsed = $this->parse($refreshToken);

        if ($parsed === null) {
            return;
        }

        [$sessionId, $secret] = $parsed;

        $this->db->transaction(function () use ($sessionId, $secret): void {
            $session = MobileSession::query()->with('user')->whereKey($sessionId)->lockForUpdate()->first();
            $presented = hash('sha256', $secret);

            if ($session !== null && (hash_equals($session->refresh_hash, $presented) || $this->matchesPrevious($session, $presented))) {
                $this->revoke($session, MobileSessionRevokeReasonEnum::Logout);
            }
        });
    }

    public function revoke(MobileSession $session, MobileSessionRevokeReasonEnum $reason): void
    {
        $this->db->transaction(function () use ($session, $reason): void {
            $session->loadMissing('user');

            $locked = MobileSession::query()->whereKey($session->id)->lockForUpdate()->first();

            if ($locked === null) {
                return;
            }

            $tokenId = $locked->personal_access_token_id;

            $locked->delete();

            if ($tokenId !== null) {
                PersonalAccessToken::query()->whereKey($tokenId)->delete();
            }

            activity()
                ->causedBy($session->user)
                ->withProperties(['ip' => get_client_ip(), 'session_id' => $session->id, 'reason' => $reason->value])
                ->log('mobile_session_revoked');
        });
    }

    public function revokeAllFor(User $user, MobileSessionRevokeReasonEnum $reason, ?MobileSession $except = null): void
    {
        $this->db->transaction(function () use ($user, $reason, $except): void {
            MobileSession::query()
                ->where('user_id', $user->id)
                ->when($except !== null, fn ($query) => $query->whereKeyNot($except?->id))
                ->get()
                ->each(fn (MobileSession $session) => $this->revoke($session->setRelation('user', $user), $reason));
        });
    }

    /** Also drops device rows that never had a session, so a raw-token registration cannot outlive the membership. */
    public function revokeAllIfNoActiveMembership(User $user): void
    {
        if ($user->hasActiveMembership()) {
            return;
        }

        $this->revokeAllFor($user, MobileSessionRevokeReasonEnum::MembershipLost);
        $this->devices->removeAllFor($user);
    }

    public function currentFor(User $user): ?MobileSession
    {
        /** @var PersonalAccessToken|TransientToken|null $token */
        $token = $user->currentAccessToken();

        if (! $token instanceof PersonalAccessToken) {
            return null;
        }

        return MobileSession::query()->where('personal_access_token_id', $token->getKey())->first();
    }

    /** Lang key of the first failing access guard, null when the user may use the mobile app. */
    public function mobileAccessDenial(User $user): ?string
    {
        return match (true) {
            ! $user->is_active => 'app.account_inactive',
            ! $user->hasActiveMembership() => 'app.no_active_tenant',
            ! $this->snapshot->hasMobileAccess($user) => 'app.mobile_forbidden',
            default => null,
        };
    }

    public function pruneExpired(): int
    {
        return $this->db->transaction(function (): int {
            $expired = MobileSession::query()->where('expires_at', '<', now());
            $tokenIds = (clone $expired)->whereNotNull('personal_access_token_id')->pluck('personal_access_token_id')->all();

            $deleted = $expired->toBase()->delete();

            PersonalAccessToken::query()->whereKey($tokenIds)->delete();

            return $deleted;
        });
    }

    private function kill(MobileSession $session, MobileSessionRevokeReasonEnum $reason): null
    {
        $this->revoke($session, $reason);

        return null;
    }

    private function replaceAccessToken(MobileSession $session, User $user): string
    {
        if ($session->personal_access_token_id !== null) {
            PersonalAccessToken::query()->whereKey($session->personal_access_token_id)->delete();
        }

        $token = $user->createToken('mobile', ['mobile'], now()->addMinutes(config()->integer('mobile_auth.access_token_minutes')));

        $session->personal_access_token_id = $token->accessToken->id;
        $session->save();

        return $token->plainTextToken;
    }

    private function newExpiry(): Carbon
    {
        return now()->addDays(config()->integer('mobile_auth.refresh_idle_days'));
    }

    private function matchesPrevious(MobileSession $session, string $presented): bool
    {
        return $session->previous_refresh_hash !== null && hash_equals($session->previous_refresh_hash, $presented);
    }

    private function previousIsAcceptable(MobileSession $session): bool
    {
        return $session->rotation_confirmed_at === null
            || ($session->rotated_at !== null && $session->rotated_at->gte(now()->subSeconds(config()->integer('mobile_auth.reuse_leeway_seconds'))));
    }

    /** @return array{0: string, 1: string}|null */
    private function parse(string $refreshToken): ?array
    {
        $parts = explode('.', $refreshToken, 2);

        if (count($parts) !== 2 || ! Str::isUuid($parts[0]) || $parts[1] === '') {
            return null;
        }

        return [$parts[0], $parts[1]];
    }
}
