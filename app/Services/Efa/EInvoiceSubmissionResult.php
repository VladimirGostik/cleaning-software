<?php

declare(strict_types=1);

namespace App\Services\Efa;

use App\Enums\EfaStatusEnum;

final readonly class EInvoiceSubmissionResult
{
    public function __construct(
        public bool $accepted,
        public ?string $providerId,
        public EfaStatusEnum $status,
        public ?string $message = null,
    ) {}
}
