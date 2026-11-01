<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasUuids;
use App\Enums\DocumentTypeEnum;
use Database\Factories\DocumentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $documentable_type
 * @property string $documentable_id
 * @property string|null $document_template_id
 * @property DocumentTypeEnum $type
 * @property string $title
 * @property string|null $body
 * @property bool $show_header
 * @property bool $show_footer
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 * @property DocumentTemplate|null $template
 * @property Contract|Quote|TenantMembership|Model $documentable
 */
#[Fillable(['tenant_id', 'documentable_type', 'documentable_id', 'document_template_id', 'type', 'title', 'body', 'show_header', 'show_footer'])]
final class Document extends Model implements HasMedia
{
    /** @use HasFactory<DocumentFactory> */
    use BelongsToTenant, HasFactory, HasUuids, InteractsWithMedia, LogsActivity, SoftDeletes;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'type' => DocumentTypeEnum::class,
            'body' => 'encrypted',
            'show_header' => 'boolean',
            'show_footer' => 'boolean',
        ];
    }

    public function registerMediaCollections(): void
    {
        $disk = config('documents.disk', 'local');
        $disk = is_string($disk) ? $disk : 'local';

        $this->addMediaCollection('file')->singleFile()->useDisk($disk);
        $this->addMediaCollection('pdf')->singleFile()->useDisk($disk);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['type', 'title', 'document_template_id'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    /** @return MorphTo<Model, $this> */
    public function documentable(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return BelongsTo<DocumentTemplate, $this> */
    public function template(): BelongsTo
    {
        return $this->belongsTo(DocumentTemplate::class, 'document_template_id')->withTrashed();
    }
}
