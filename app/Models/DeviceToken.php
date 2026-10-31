<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\HasUuids;
use App\Enums\DevicePlatformEnum;
use Database\Factories\DeviceTokenFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $user_id
 * @property int|null $personal_access_token_id
 * @property string $token
 * @property DevicePlatformEnum $platform
 * @property string|null $app_version
 * @property Carbon $last_seen_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property User $user
 */
#[Fillable(['user_id', 'personal_access_token_id', 'token', 'platform', 'app_version', 'last_seen_at'])]
final class DeviceToken extends Model
{
    /** @use HasFactory<DeviceTokenFactory> */
    use HasFactory, HasUuids;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'platform' => DevicePlatformEnum::class,
            'last_seen_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
