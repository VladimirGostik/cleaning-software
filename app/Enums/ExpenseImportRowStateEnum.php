<?php

declare(strict_types=1);

namespace App\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum ExpenseImportRowStateEnum: string
{
    case New = 'new';
    case AlreadyImported = 'already_imported';
    case PreviouslyDeleted = 'previously_deleted';
    case PossibleMatch = 'possible_match';
}
