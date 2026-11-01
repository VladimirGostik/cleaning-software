<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\HasUuids;
use Database\Factories\MobileSessionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $user_id
 * @property int|null $personal_access_token_id
 * @property string $refresh_hash
 * @property string|null $previous_refresh_hash
 * @property Carbon|null $rotated_at
 * @property Carbon|null $rotation_confirmed_at
 * @property Carbon $expires_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property User $user
 */
#[Fillable(['user_id', 'personal_access_token_id', 'refresh_hash', 'previous_refresh_hash', 'rotated_at', 'rotation_confirmed_at', 'expires_at'])]
#[Hidden(['refresh_hash', 'previous_refresh_hash'])]
final class MobileSession extends Model
{
    /** @use HasFactory<MobileSessionFactory> */
    use HasFactory, HasUuids;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'rotated_at' => 'datetime',
            'rotation_confirmed_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<DeviceToken, $this> */
    public function deviceTokens(): HasMany
    {
        return $this->hasMany(DeviceToken::class);
    }
}
