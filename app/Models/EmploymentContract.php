<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\HasUuids;
use App\Enums\EmploymentContractTypeEnum;
use Database\Factories\EmploymentContractFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property EmploymentContractTypeEnum $employment_type
 * @property float|null $hourly_rate
 * @property float|null $monthly_salary
 * @property float|null $weekly_hours
 * @property Carbon|null $probation_end_date
 */
#[Fillable([
    'contract_id',
    'employment_type',
    'position',
    'hourly_rate',
    'monthly_salary',
    'weekly_hours',
    'probation_end_date',
])]
final class EmploymentContract extends Model
{
    /** @use HasFactory<EmploymentContractFactory> */
    use HasFactory, HasUuids;

    /**
     * @return array<string, string>
     */
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

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }
}
