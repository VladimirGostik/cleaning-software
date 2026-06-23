<?php

declare(strict_types=1);

namespace App\Data\Schedule;

use Illuminate\Support\Facades\App;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class WorkBreakdownUpsertData extends Data
{
    public function __construct(
        #[Required]
        public string $cleaning_object_id,
        #[Required, Max(255)]
        public string $name,
        public bool $is_active = true,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        $tenantId = App::make('current_tenant_id');

        return [
            'cleaning_object_id' => [
                'required',
                'uuid',
                Rule::exists('objects', 'id')->where('tenant_id', $tenantId)->whereNull('deleted_at'),
            ],
        ];
    }
}
