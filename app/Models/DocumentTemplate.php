<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\HasUuids;
use App\Enums\DocumentTemplateScopeEnum;
use App\Enums\DocumentTemplateStateEnum;
use App\Enums\DocumentTypeEnum;
use App\Enums\EmploymentContractTypeEnum;
use App\Scopes\TenantOrGlobalScope;
use Database\Factories\DocumentTemplateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * @property string $id
 * @property string|null $tenant_id
 * @property string|null $parent_id
 * @property DocumentTypeEnum $type
 * @property EmploymentContractTypeEnum|null $employment_type
 * @property string $name
 * @property string $body
 * @property string|null $base_body
 * @property string|null $base_hash
 * @property bool $show_header
 * @property bool $show_footer
 * @property bool $is_active
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 * @property DocumentTemplate|null $parent
 */
#[Fillable(['tenant_id', 'parent_id', 'type', 'employment_type', 'name', 'body', 'base_body', 'base_hash', 'show_header', 'show_footer', 'is_active'])]
#[ScopedBy([TenantOrGlobalScope::class])]
final class DocumentTemplate extends Model
{
    /** @use HasFactory<DocumentTemplateFactory> */
    use HasFactory, HasUuids, LogsActivity, SoftDeletes;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'type' => DocumentTypeEnum::class,
            'employment_type' => EmploymentContractTypeEnum::class,
            'show_header' => 'boolean',
            'show_footer' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'type', 'employment_type', 'is_active', 'parent_id'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    /** @return BelongsTo<DocumentTemplate, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id')->withoutGlobalScopes()->withTrashed();
    }

    /** @return HasMany<DocumentTemplate, $this> */
    public function overrides(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function templateScope(): DocumentTemplateScopeEnum
    {
        if ($this->tenant_id === null) {
            return DocumentTemplateScopeEnum::Global;
        }

        return $this->parent_id !== null ? DocumentTemplateScopeEnum::Override : DocumentTemplateScopeEnum::Firm;
    }

    public function state(): ?DocumentTemplateStateEnum
    {
        if ($this->parent_id === null) {
            return null;
        }

        $parent = $this->parent;

        if ($parent !== null && hash('sha256', $parent->body) !== $this->base_hash) {
            return DocumentTemplateStateEnum::BaseChanged;
        }

        return $this->body === $this->base_body ? DocumentTemplateStateEnum::Base : DocumentTemplateStateEnum::Customized;
    }

    /**
     * @param  Builder<DocumentTemplate>  $query
     * @return Builder<DocumentTemplate>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
