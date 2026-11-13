<?php

declare(strict_types=1);

namespace App\Data\Schedule;

use App\Rules\AllowedUploadFile;
use Illuminate\Http\UploadedFile;
use Spatie\LaravelData\Attributes\MergeValidationRules;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Attributes\Validation\Uuid;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\LiteralTypeScriptType;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
#[MergeValidationRules]
final class JobPhotoUploadData extends Data
{
    public function __construct(
        #[LiteralTypeScriptType('File')]
        public readonly UploadedFile $file,
        #[Required, Uuid]
        public readonly string $client_uuid,
    ) {}

    /** @return array<string, mixed> */
    public static function rules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                'max:'.config()->integer('scheduling.work_records.photos.max_size_kb'),
                new AllowedUploadFile('scheduling.work_records.photos', 'app.job_photo_invalid_type'),
            ],
        ];
    }

    /** @return array<string, string> */
    public static function messages(): array
    {
        $max = (int) (config()->integer('scheduling.work_records.photos.max_size_kb') / 1024);

        return [
            'file.max' => __('app.job_photo_too_large', ['max' => $max]),
            'file.uploaded' => __('app.job_photo_too_large', ['max' => $max]),
        ];
    }
}
