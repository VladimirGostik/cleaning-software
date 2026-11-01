<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\Profile\McpTokenCreateData;
use App\Data\Profile\McpTokenCreatedData;
use App\Data\Profile\McpTokenData;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

final readonly class McpTokenService
{
    private const int MAX_TOKENS = 10;

    private const int LIFETIME_DAYS = 180;

    /** @return list<McpTokenData> */
    public function listFor(User $user): array
    {
        return array_values($this->mcpTokens($user)->map(McpTokenData::fromToken(...))->all());
    }

    public function create(User $user, McpTokenCreateData $data): McpTokenCreatedData
    {
        if ($this->mcpTokens($user)->count() >= self::MAX_TOKENS) {
            throw ValidationException::withMessages(['name' => [__('app.mcp_token_limit')]]);
        }

        $created = $user->createToken($data->name, ['mcp'], now()->addDays(self::LIFETIME_DAYS));

        return new McpTokenCreatedData(McpTokenData::fromToken($created->accessToken), $created->plainTextToken);
    }

    /** Only the owner's own tokens whose sole ability is `mcp` resolve; anything else (including `mobile` tokens) is not found. */
    public function findOwnMcpToken(User $user, int $id): PersonalAccessToken
    {
        $token = $this->mcpTokens($user)->first(fn (PersonalAccessToken $token): bool => $token->getKey() === $id);

        if ($token === null) {
            throw (new ModelNotFoundException)->setModel(PersonalAccessToken::class, [$id]);
        }

        return $token;
    }

    public function revoke(PersonalAccessToken $token): void
    {
        $token->delete();
    }

    /** @return Collection<int, PersonalAccessToken> */
    private function mcpTokens(User $user): Collection
    {
        /** @var Collection<int, PersonalAccessToken> $tokens */
        $tokens = $user->tokens()
            ->where('abilities', 'like', '%"mcp"%')
            ->orderByDesc('created_at')
            ->get()
            ->filter(fn (PersonalAccessToken $token): bool => $token->abilities === ['mcp']);

        return $tokens;
    }
}
