<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasUuids;
use App\Enums\InvitationStatusEnum;
use Database\Factories\TenantInvitationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

#[Fillable([
    'tenant_id',
    'invited_by_user_id',
    'email',
    'role_name',
    'token',
    'status',
    'expires_at',
    'accepted_at',
])]
final class TenantInvitation extends Model
{
    /** @use HasFactory<TenantInvitationFactory> */
    use BelongsToTenant, HasFactory, HasUuids, LogsActivity, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => InvitationStatusEnum::class,
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['email', 'role_name', 'status', 'expires_at', 'accepted_at'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by_user_id');
    }

    public function isAcceptable(): bool
    {
        return $this->status === InvitationStatusEnum::Pending
            && $this->expires_at !== null
            && $this->expires_at->isFuture();
    }

    public function markAccepted(): void
    {
        $this->forceFill([
            'status' => InvitationStatusEnum::Accepted,
            'accepted_at' => now(),
        ])->save();
    }
}
