<?php

declare(strict_types=1);

namespace App\Data\Tasks;

use App\Data\Auth\MobileCompanyData;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\LiteralTypeScriptType;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class TaskFormContextData extends Data
{
    /**
     * @param  list<MobileCompanyData>  $companies
     * @param  array<string, list<TaskMemberOptionData>>  $members
     */
    public function __construct(
        public readonly array $companies,
        #[LiteralTypeScriptType('Record<string, App.Data.Tasks.TaskMemberOptionData[]>')]
        public readonly array $members,
    ) {}
}
