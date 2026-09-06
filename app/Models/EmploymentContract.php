<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasUuids;
use App\Enums\EmploymentContractTypeEnum;
use Database\Factories\EmploymentContractFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $contract_id
 * @property EmploymentContractTypeEnum $employment_type
 * @property string|null $position
 * @property string|null $hourly_rate
 * @property string|null $monthly_salary
 * @property string|null $weekly_hours
 * @property Carbon|null $probation_end_date
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Contract $contract
 */
#[Fillable([
    'tenant_id', 'contract_id', 'employment_type', 'position',
    'hourly_rate', 'monthly_salary', 'weekly_hours', 'probation_end_date',
])]
final class EmploymentContract extends Model
{
    /** @use HasFactory<EmploymentContractFactory> */
    use BelongsToTenant, HasFactory, HasUuids;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'employment_type' => EmploymentContractTypeEnum::class,
            'hourly_rate' => 'decimal:2',
            'monthly_salary' => 'decimal:2',
            'weekly_hours' => 'decimal:2',
            'probation_end_date' => 'date',
        ];
    }

    /** @return BelongsTo<Contract, $this> */
    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }
}
