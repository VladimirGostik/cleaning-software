<?php

declare(strict_types=1);

namespace App\Data\Tenants;

use Spatie\LaravelData\Attributes\Validation\Email;
use Spatie\LaravelData\Attributes\Validation\In;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class InviteData extends Data
{
    public function __construct(
        #[Required, Email]
        public string $email,
        #[Required, In(['Vedúca', 'Interná upratovačka', 'Sekretárka', 'Účtovníčka', 'Zákazník'])]
        public string $role_name,
    ) {}
}
