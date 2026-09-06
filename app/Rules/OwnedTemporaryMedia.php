<?php

declare(strict_types=1);

namespace App\Rules;

use App\Models\Media;
use App\Models\TemporaryUpload;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

final class OwnedTemporaryMedia implements ValidationRule
{
    /**
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || $value === '') {
            $fail('validation.exists')->translate();

            return;
        }

        $sessionId = session()->getId();
        $userId = auth()->id();

        $ownedIds = TemporaryUpload::query()
            ->where('session_id', $sessionId)
            ->when($userId !== null, fn ($q) => $q->orWhere('user_id', $userId))
            ->pluck('id');

        $exists = Media::query()
            ->where('uuid', $value)
            ->where('model_type', (new TemporaryUpload)->getMorphClass())
            ->whereIn('model_id', $ownedIds)
            ->exists();

        if (! $exists) {
            $fail('validation.exists')->translate();
        }
    }
}
