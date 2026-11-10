<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasUuids;
use App\Enums\CurrencyEnum;
use App\Enums\ExpenseCategoryEnum;
use App\Enums\ExpenseSourceEnum;
use App\Enums\ExpenseStatusEnum;
use Database\Factories\ExpenseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $created_by_user_id
 * @property ExpenseStatusEnum $status
 * @property Carbon $date
 * @property string $amount
 * @property CurrencyEnum $currency
 * @property string|null $vat_amount
 * @property string|null $supplier_name
 * @property string|null $description
 * @property ExpenseCategoryEnum|null $category
 * @property string|null $tenant_membership_id
 * @property Carbon|null $payroll_month
 * @property bool $is_undocumented
 * @property ExpenseSourceEnum $source
 * @property string|null $bank_reference
 * @property string|null $external_hash
 * @property array<string, mixed>|null $ai_proposal
 * @property Carbon|null $confirmed_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 * @property Tenant $tenant
 * @property User|null $creator
 * @property TenantMembership|null $employee
 */
#[Fillable([
    'tenant_id', 'created_by_user_id', 'status', 'date', 'amount', 'currency', 'vat_amount', 'supplier_name',
    'description', 'category', 'source', 'bank_reference', 'external_hash', 'ai_proposal', 'confirmed_at',
    'tenant_membership_id', 'payroll_month', 'is_undocumented',
])]
final class Expense extends Model implements HasMedia
{
    /** @use HasFactory<ExpenseFactory> */
    use BelongsToTenant, HasFactory, HasUuids, InteractsWithMedia, LogsActivity, SoftDeletes;

    public const string RECEIPT_COLLECTION = 'receipt';

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => ExpenseStatusEnum::class,
            'source' => ExpenseSourceEnum::class,
            'category' => ExpenseCategoryEnum::class,
            'currency' => CurrencyEnum::class,
            'date' => 'date',
            'amount' => 'decimal:2',
            'vat_amount' => 'decimal:2',
            'ai_proposal' => 'array',
            'confirmed_at' => 'datetime',
            'payroll_month' => 'date',
            'is_undocumented' => 'boolean',
        ];
    }

    public function registerMediaCollections(): void
    {
        $disk = config('expenses.receipt.disk', 'local');
        /** @var list<string> $mimes */
        $mimes = config('expenses.receipt.allowed_mimes', []);

        $this->addMediaCollection(self::RECEIPT_COLLECTION)
            ->singleFile()
            ->acceptsMimeTypes($mimes)
            ->useDisk(is_string($disk) ? $disk : 'local');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'date', 'amount', 'currency', 'vat_amount', 'supplier_name', 'category', 'confirmed_at', 'tenant_membership_id', 'payroll_month', 'is_undocumented'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    public function beforeActivityLogged(Activity $activity, string $eventName): void
    {
        $activity->tenant_id = $this->tenant_id;
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /** @return BelongsTo<TenantMembership, $this> */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(TenantMembership::class, 'tenant_membership_id');
    }

    public function isDraft(): bool
    {
        return $this->status === ExpenseStatusEnum::Draft;
    }
}
