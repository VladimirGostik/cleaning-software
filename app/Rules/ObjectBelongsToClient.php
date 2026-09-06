<?php

declare(strict_types=1);

namespace App\Rules;

use App\Models\CleaningObject;
use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;

final class ObjectBelongsToClient implements DataAwareRule, ValidationRule
{
    /** @var array<string, mixed> */
    private array $data = [];

    /**
     * @param  array<string, mixed>  $data
     */
    public function setData(array $data): self
    {
        $this->data = $data;

        return $this;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null) {
            return;
        }

        $clientId = $this->data['client_id'] ?? null;

        if ($clientId === null) {
            $fail(__('app.object_requires_client'));

            return;
        }

        $exists = CleaningObject::withoutGlobalScopes()
            ->where('id', $value)
            ->where('client_id', $clientId)
            ->whereNull('deleted_at')
            ->exists();

        if (! $exists) {
            $fail(__('app.object_not_of_client'));
        }
    }
}
