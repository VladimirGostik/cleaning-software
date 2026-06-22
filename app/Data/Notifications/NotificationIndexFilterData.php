<?php

declare(strict_types=1);

namespace App\Data\Notifications;

use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Data;

final class NotificationIndexFilterData extends Data
{
    public function __construct(
        #[MapInputName('filter.unreadOnly')]
        public ?bool $unreadOnly = null,
        #[MapInputName('filter.type')]
        public ?string $type = null,
        #[MapInputName('filter.perPage')]
        public int $perPage = 20,
    ) {
        $this->perPage = max(1, min($this->perPage, 100));
    }
}
