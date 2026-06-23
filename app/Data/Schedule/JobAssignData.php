<?php

declare(strict_types=1);

namespace App\Data\Schedule;

use Illuminate\Support\Facades\App;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class JobAssignData extends Data
{
    public function __construct(
        public ?string $assigned_membership_id = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        $tenantId = App::make('current_tenant_id');

        return [
            'assigned_membership_id' => [
                'nullable',
                'uuid',
                Rule::exists('tenant_memberships', 'id')->where('tenant_id', $tenantId)->where('is_active', true),
            ],
        ];
    }
}
