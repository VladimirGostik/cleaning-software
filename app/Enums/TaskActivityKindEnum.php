<?php

declare(strict_types=1);

namespace App\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum TaskActivityKindEnum: string
{
    case Created = 'created';
    case Updated = 'updated';
    case AttachmentAdded = 'attachment_added';
    case AttachmentDeleted = 'attachment_deleted';
}
