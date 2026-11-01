<?php

declare(strict_types=1);

namespace App\Rules;

use App\Enums\DocumentTypeEnum;
use App\Models\DocumentTemplate;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final readonly class UsableDocumentTemplate implements ValidationRule
{
    public function __construct(private ?DocumentTypeEnum $type = null) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail('validation.exists')->translate();

            return;
        }

        $usable = DocumentTemplate::query()
            ->whereKey($value)
            ->where('is_active', true)
            ->when($this->type !== null, fn ($query) => $query->where('type', $this->type?->value))
            ->exists();

        if (! $usable) {
            $fail('validation.exists')->translate();
        }
    }
}
