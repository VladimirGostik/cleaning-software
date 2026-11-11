<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\ActivityRevertChangeData;
use App\Data\ActivityRevertData;
use App\Enums\ActivityRevertBlockReasonEnum as Reason;
use App\Enums\ActivityRevertStatusEnum;
use App\Enums\PermissionEnum;
use App\Models\Activity;
use App\Models\CleaningObject;
use App\Models\Client;
use App\Models\Contact;
use App\Models\Contract;
use App\Models\Document;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Quote;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\TenantInterface;
use App\Models\User;
use App\Support\ActivityBatch;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * @phpstan-type Ctx array{size: int, restored: array<string, true>, subjects: array<string, true>}
 * @phpstan-type Verdict array{0: ?Reason, 1: ?int}
 */
final readonly class ActivityRevertService
{
    private const array EVENTS = ['created', 'updated', 'deleted', 'restored'];

    private const array CONTACT_FIELDS = ['name', 'position', 'email', 'phone'];

    private const array OBJECT_FIELDS = [
        'type', 'name', 'street', 'city', 'postal_code', 'country', 'access_code', 'key_box_code',
        'key_count', 'special_instructions', 'area_sqm', 'floor',
        'billing_name', 'billing_ico', 'billing_dic', 'billing_vat_number',
        'billing_peppol_participant_id', 'billing_street', 'billing_city', 'billing_postal_code', 'billing_email',
    ];

    /** Allowlist: model => fields an `updated` row may write back (is_primary of contacts is added for whole batches). */
    private const array UPDATE_FIELDS = [
        Client::class => [
            'type', 'name', 'ico', 'dic', 'vat_number', 'is_vat_payer', 'street', 'city',
            'postal_code', 'country', 'peppol_participant_id',
        ],
        Contact::class => self::CONTACT_FIELDS,
        CleaningObject::class => self::OBJECT_FIELDS,
        Task::class => ['title', 'description', 'priority', 'due_at', 'remind_at'],
        Expense::class => ['date', 'amount', 'currency', 'vat_amount', 'supplier_name', 'category'],
        Tenant::class => [
            'name', 'ico', 'dic', 'vat_number', 'is_vat_payer', 'vat_rate', 'iban', 'swift_bic',
            'registration_info', 'representative_name', 'signing_place', 'address_line', 'city',
            'postal_code', 'country', 'contact_email', 'contact_phone', 'peppol_participant_id',
        ],
        TenantInterface::class => [
            'color', 'recurring_default_state', 'default_constant_symbol', 'default_payment_type',
            'default_currency', 'default_rounding_mode', 'default_header_text', 'default_footer_text',
            'default_quote_included_text', 'invoice_mail_subject', 'invoice_mail_body',
        ],
        Invoice::class => [],
        Quote::class => [],
        Contract::class => [],
    ];

    /** Models whose `created` / `restored` row is reverted by deleting the subject. */
    private const array REMOVABLE = [
        Client::class, Contact::class, Task::class,
        Expense::class, Invoice::class, Quote::class, Contract::class,
    ];

    /** Models whose `deleted` row is reverted by restoring the subject (`restored` rows may also delete CleaningObject). */
    private const array RESTORABLE = [
        Client::class, Contact::class, CleaningObject::class,
        Task::class, Expense::class, Invoice::class, Quote::class, Contract::class,
    ];

    public function __construct(private DatabaseManager $db) {}

    /**
     * @param  Collection<int, Activity>  $activities
     * @return array<int, ActivityRevertData>
     */
    public function describeMany(Collection $activities, User $actor): array
    {
        $this->attachSubjects($activities);
        $links = $this->links($activities);
        $result = [];

        foreach ($activities as $activity) {
            $size = $links['sizes'][(string) $activity->batch_uuid] ?? 1;
            $verdict = $this->evaluate($activity, $actor, ['size' => $size, 'restored' => [], 'subjects' => []], false);
            $result[$activity->id] = $this->data($activity, $verdict, $size, $links);
        }

        return $result;
    }

    public function describe(Activity $activity, User $actor): ActivityRevertData
    {
        $members = $this->members($activity);
        $verdict = $this->fullVerdict($activity, $actor, $members);
        $links = $this->links(collect([$activity]));

        return $this->data($activity, $verdict, $members->count(), $links);
    }

    /**
     * @param  Collection<int, Activity>  $activities
     * @return array<int, string|null>
     */
    public function subjectLabels(Collection $activities): array
    {
        $this->attachSubjects($activities);

        return $activities->mapWithKeys(fn (Activity $a): array => [$a->id => $this->label($a)])->all();
    }

    /** @return list<ActivityRevertChangeData> */
    public function changes(Activity $activity): array
    {
        $this->attachSubjects(collect([$activity]));

        $class = $this->modelClass($activity);
        $subject = $activity->subject;
        $logged = $activity->attributeChangesArray() ?? [];
        $old = is_array($logged['old'] ?? null) ? $logged['old'] : [];
        $new = is_array($logged['attributes'] ?? null) ? $logged['attributes'] : [];
        $isDeleted = $activity->event === 'deleted';
        $current = $subject instanceof Model ? $this->currentValues($subject) : null;
        $kind = $class !== null ? $this->kind((string) $activity->event, $class) : null;
        $revertable = $class !== null ? $this->revertableFields($class, $this->batchSize($activity)) : [];
        $hidden = $class !== null ? ($subject ?? new $class) : null;
        $changes = [];

        foreach (array_keys($isDeleted ? $old : $new) as $field) {
            $field = (string) $field;
            $masked = $hidden instanceof Model && $this->isMasked($hidden, $field);
            $conflict = ! $masked
                && $kind === 'update'
                && $current !== null
                && in_array($field, $revertable, true)
                && ! $this->same($current[$field] ?? null, $new[$field] ?? null);
            $label = __('validation.attributes.'.$field);

            $changes[] = new ActivityRevertChangeData(
                field: $field,
                label: $label === 'validation.attributes.'.$field ? $field : $label,
                old: $masked || $activity->event === 'created' || $activity->event === 'restored' ? null : ($old[$field] ?? null),
                new: $masked || $isDeleted ? null : ($new[$field] ?? null),
                current: $masked || $current === null ? null : ($current[$field] ?? null),
                conflict: $conflict,
                masked: $masked,
            );
        }

        return $changes;
    }

    public function revert(Activity $activity, User $actor): void
    {
        try {
            $this->db->transaction(fn () => $this->revertLocked($activity, $actor));
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['status' => [Reason::UniqueConflict->label()]]);
        }
    }

    private function revertLocked(Activity $activity, User $actor): void
    {
        $rows = Activity::query()
            ->when(
                $activity->batch_uuid !== null,
                fn (Builder $q) => $q->where('tenant_id', $activity->tenant_id)->where('batch_uuid', $activity->batch_uuid),
                fn (Builder $q) => $q->whereKey($activity->id),
            )
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        $locked = $rows->firstWhere('id', $activity->id);

        if ($locked === null || $locked->reverted_at !== null) {
            throw ValidationException::withMessages(['status' => [Reason::AlreadyReverted->label()]]);
        }

        $members = $rows->sortByDesc('id')->values();
        [$reason] = $this->fullVerdict($locked, $actor, $members, true);

        if ($reason === Reason::Forbidden) {
            throw new AuthorizationException;
        }

        if ($reason !== null) {
            throw ValidationException::withMessages(['status' => [$reason->label()]]);
        }

        $pending = $members->filter(fn (Activity $m): bool => $m->reverted_at === null)->values();
        $ctx = $this->context($members);

        ActivityBatch::run(function () use ($pending, $ctx): void {
            foreach ($pending as $member) {
                $this->apply($member, $ctx);
            }

            Activity::query()->whereIn('id', $pending->pluck('id'))->update([
                'reverted_at' => now(),
                'revert_batch_uuid' => ActivityBatch::current(),
            ]);
        });
    }

    /**
     * @param  Collection<int, Activity>  $members
     * @return Verdict
     */
    private function fullVerdict(Activity $activity, User $actor, Collection $members, bool $lock = false): array
    {
        $this->attachSubjects($members, $lock);
        $ctx = $this->context($members);

        $own = $this->evaluate($activity, $actor, $ctx, true);

        if ($own[0] !== null) {
            return $own;
        }

        foreach ($members as $member) {
            if ($member->id === $activity->id || $member->reverted_at !== null || $this->isSatellite($member)) {
                continue;
            }

            if ($this->evaluate($member, $actor, $ctx, true)[0] !== null) {
                return [Reason::BatchMemberBlocked, $member->id];
            }
        }

        return [null, null];
    }

    /**
     * @param  Collection<int, Activity>  $members
     * @return Ctx
     */
    private function context(Collection $members): array
    {
        $restored = [];
        $subjects = [];

        foreach ($members as $member) {
            $class = $this->modelClass($member);

            if ($class === null) {
                continue;
            }

            $key = $this->key($class, $member->subject_id);
            $subjects[$key] = true;

            if ($this->kind((string) $member->event, $class) === 'restore') {
                $restored[$key] = true;
            }
        }

        return ['size' => $members->count(), 'restored' => $restored, 'subjects' => $subjects];
    }

    /** @return Collection<int, Activity> */
    private function members(Activity $activity): Collection
    {
        if ($activity->batch_uuid === null) {
            return collect([$activity]);
        }

        return Activity::query()
            ->where('tenant_id', $activity->tenant_id)
            ->where('batch_uuid', $activity->batch_uuid)
            ->orderByDesc('id')
            ->get()
            ->map(fn (Activity $m): Activity => $m->id === $activity->id ? $activity : $m)
            ->values();
    }

    private function batchSize(Activity $activity): int
    {
        if ($activity->batch_uuid === null) {
            return 1;
        }

        return Activity::query()->where('tenant_id', $activity->tenant_id)->where('batch_uuid', $activity->batch_uuid)->count();
    }

    /**
     * @param  Ctx  $ctx
     * @return Verdict
     */
    private function evaluate(Activity $activity, User $actor, array $ctx, bool $full): array
    {
        $tenantId = current_tenant_id();

        if ($activity->reverted_at !== null) {
            return [Reason::AlreadyReverted, null];
        }

        if ($activity->tenant_id === null) {
            return [Reason::NoCompany, null];
        }

        if ($activity->tenant_id !== $tenantId) {
            return [Reason::Forbidden, null];
        }

        $event = (string) $activity->event;

        if (! in_array($event, self::EVENTS, true)) {
            return [Reason::UnsupportedOperation, null];
        }

        $class = $this->modelClass($activity);

        if ($class === null) {
            return [Reason::UnsupportedModel, null];
        }

        $kind = $this->kind($event, $class);

        if ($kind === null) {
            return [Reason::UnsupportedOperation, null];
        }

        $keys = $this->loggedKeys($activity);

        if ($kind === 'update') {
            if ($keys === []) {
                return [Reason::NoChanges, null];
            }

            if (array_diff($keys, $this->revertableFields($class, $ctx['size'])) !== []) {
                return [Reason::LifecycleField, null];
            }
        }

        $subject = $activity->subject;

        if (! $subject instanceof Model || ! $this->belongsToTenant($subject, $tenantId)) {
            return [Reason::SubjectMissing, null];
        }

        $trashed = $this->isTrashed($subject);

        if ($kind === 'restore' ? ! $trashed : $trashed) {
            return [$kind === 'restore' ? Reason::SubjectNotDeleted : Reason::SubjectDeleted, null];
        }

        if ($kind === 'restore' && ($full || $ctx['size'] === 1) && $this->parentTrashed($subject, $class, $ctx)) {
            return [Reason::ParentDeleted, null];
        }

        if ($this->notDraft($subject, $kind)) {
            return [Reason::NotDraft, null];
        }

        if ($kind === 'remove' && $ctx['size'] === 1 && $subject instanceof Contact && $subject->is_primary) {
            return [Reason::PrimaryContact, null];
        }

        if (! $this->allowed($actor, $subject, $kind)) {
            return [Reason::Forbidden, null];
        }

        if (! $full) {
            return [null, null];
        }

        if ($kind === 'update' && $this->hasConflict($activity, $subject)) {
            return [Reason::Conflict, null];
        }

        if ($kind === 'remove' && ($this->hasLaterChanges($activity) || $this->hasForeignChildren($subject, $ctx))) {
            return [Reason::HasLaterChanges, null];
        }

        return [null, null];
    }

    /** @return class-string<Model>|null */
    private function modelClass(Activity $activity): ?string
    {
        if ($activity->subject_type === null || $activity->subject_id === null) {
            return null;
        }

        $class = Relation::getMorphedModel($activity->subject_type) ?? $activity->subject_type;

        /** @var class-string<Model> $class */
        return array_key_exists($class, self::UPDATE_FIELDS) ? $class : null;
    }

    private function kind(string $event, string $class): ?string
    {
        return match ($event) {
            'updated' => 'update',
            'created' => in_array($class, self::REMOVABLE, true) ? 'remove' : null,
            'restored' => in_array($class, self::REMOVABLE, true) || $class === CleaningObject::class ? 'remove' : null,
            'deleted' => in_array($class, self::RESTORABLE, true) ? 'restore' : null,
            default => null,
        };
    }

    /** @return list<string> */
    private function revertableFields(string $class, int $batchSize): array
    {
        $fields = self::UPDATE_FIELDS[$class];

        if ($batchSize > 1 && $class === Contact::class) {
            $fields[] = 'is_primary';
        }

        return $fields;
    }

    /** @return list<string> */
    private function loggedKeys(Activity $activity): array
    {
        $logged = $activity->attributeChangesArray() ?? [];
        $side = $activity->event === 'deleted' ? ($logged['old'] ?? null) : ($logged['attributes'] ?? null);

        return is_array($side) ? array_map(strval(...), array_keys($side)) : [];
    }

    /** @return array<string, mixed> */
    private function loggedSide(Activity $activity, string $side): array
    {
        $logged = $activity->attributeChangesArray() ?? [];
        $values = $logged[$side] ?? null;

        /** @var array<string, mixed> */
        return is_array($values) ? $values : [];
    }

    private function belongsToTenant(Model $subject, string $tenantId): bool
    {
        return match (true) {
            $subject instanceof Tenant => $subject->getKey() === $tenantId,
            $subject instanceof Task, $subject instanceof TenantInterface => $subject->tenant_id === $tenantId,
            default => true,
        };
    }

    /** @param  Ctx  $ctx */
    private function parentTrashed(Model $subject, string $class, array $ctx): bool
    {
        $relation = match (true) {
            $subject instanceof CleaningObject => 'client',
            $subject instanceof Contact => 'contactable',
            default => null,
        };

        if ($relation === null) {
            return false;
        }

        $parent = $subject->getRelationValue($relation);

        if (! $parent instanceof Model) {
            return true;
        }

        return $this->isTrashed($parent) && ! isset($ctx['restored'][$this->key($parent::class, $parent->getKey())]);
    }

    private function notDraft(Model $subject, string $kind): bool
    {
        return match (true) {
            $kind === 'remove' && $subject instanceof Quote => ! $subject->isDeletable(),
            $kind === 'remove' && ($subject instanceof Invoice || $subject instanceof Contract) => ! $subject->isEditable(),
            $kind === 'update' && $subject instanceof Expense => ! $subject->isDraft(),
            default => false,
        };
    }

    private function allowed(User $actor, Model $subject, string $kind): bool
    {
        if ($subject instanceof Tenant || $subject instanceof TenantInterface) {
            return $actor->can(PermissionEnum::ManageBillingSettings->value);
        }

        if ($subject instanceof Contact) {
            $parent = $subject->getRelationValue('contactable');

            return ($parent instanceof Client || $parent instanceof CleaningObject) && $actor->can('update', $parent);
        }

        return $actor->can($kind === 'update' ? 'update' : 'delete', $subject);
    }

    private function hasConflict(Activity $activity, Model $subject): bool
    {
        $current = $this->currentValues($subject);
        $new = $this->loggedSide($activity, 'attributes');

        foreach ($this->loggedKeys($activity) as $key) {
            if (! $this->same($current[$key] ?? null, $new[$key] ?? null)) {
                return true;
            }
        }

        return false;
    }

    private function hasLaterChanges(Activity $activity): bool
    {
        $revertedRevertBatches = Activity::query()
            ->select('revert_batch_uuid')
            ->whereNotNull('reverted_at')
            ->whereNotNull('revert_batch_uuid');

        return Activity::query()
            ->where('subject_type', $activity->subject_type)
            ->where('subject_id', $activity->subject_id)
            ->where('id', '>', $activity->id)
            ->whereIn('event', self::EVENTS)
            ->whereNull('reverted_at')
            ->when($activity->batch_uuid !== null, fn (Builder $q) => $q->where(
                fn (Builder $inner) => $inner->whereNull('batch_uuid')->orWhere('batch_uuid', '<>', $activity->batch_uuid),
            ))
            ->where(fn (Builder $q) => $q->whereNull('batch_uuid')->orWhereNotIn('batch_uuid', $revertedRevertBatches))
            ->exists();
    }

    /** @param  Ctx  $ctx */
    private function hasForeignChildren(Model $subject, array $ctx): bool
    {
        if (! $subject instanceof Client) {
            return false;
        }

        $objectIds = CleaningObject::query()->where('client_id', $subject->id)->pluck('id');
        $contactIds = Contact::query()
            ->where(fn (Builder $query) => $query
                ->whereMorphedTo('contactable', $subject)
                ->orWhere(fn (Builder $query) => $query
                    ->where('contactable_type', Relation::getMorphAlias(CleaningObject::class))
                    ->whereIn('contactable_id', $objectIds)))
            ->pluck('id');

        return $contactIds->contains(fn (mixed $id): bool => ! isset($ctx['subjects'][$this->key(Contact::class, $id)]))
            || $objectIds->contains(fn (mixed $id): bool => ! isset($ctx['subjects'][$this->key(CleaningObject::class, $id)]));
    }

    /** @param  Ctx  $ctx */
    private function apply(Activity $member, array $ctx): void
    {
        $class = $this->modelClass($member);
        $kind = $class !== null ? $this->kind((string) $member->event, $class) : null;

        if ($class === null || $kind === null) {
            return;
        }

        $subject = $this->query($class)->lockForUpdate()->find($member->subject_id);

        if ($subject === null) {
            return;
        }

        $trashed = $this->isTrashed($subject);

        if ($kind === 'restore') {
            if ($trashed) {
                $this->restore($subject, $ctx['size']);
            }

            return;
        }

        if ($trashed) {
            return;
        }

        if ($kind === 'update') {
            $subject->forceFill($this->oldValues($member))->save();

            return;
        }

        $this->remove($subject);
    }

    private function isTrashed(Model $model): bool
    {
        return method_exists($model, 'trashed') && $model->trashed();
    }

    /** @return array<string, mixed> */
    private function oldValues(Activity $member): array
    {
        $old = $this->loggedSide($member, 'old');

        return array_intersect_key($old, array_flip($this->loggedKeys($member)));
    }

    private function remove(Model $subject): void
    {
        match (true) {
            $subject instanceof Client => app(ClientService::class)->delete($subject),
            $subject instanceof Task => app(TaskService::class)->delete($subject),
            $subject instanceof Expense => app(ExpenseService::class)->delete($subject),
            $subject instanceof Invoice => app(InvoiceService::class)->delete($subject),
            $subject instanceof Quote => app(QuoteService::class)->delete($subject),
            $subject instanceof Contract => app(ContractService::class)->delete($subject),
            default => $subject->delete(),
        };
    }

    private function isSatellite(Activity $member): bool
    {
        if ($member->subject_type === null) {
            return false;
        }

        return (Relation::getMorphedModel($member->subject_type) ?? $member->subject_type) === Document::class;
    }

    private function restore(Model $subject, int $batchSize): void
    {
        if ($batchSize === 1 && $subject instanceof Contact) {
            $subject->forceFill(['is_primary' => ! Contact::query()->where('contactable_type', $subject->contactable_type)->where('contactable_id', $subject->contactable_id)->where('is_primary', true)->exists()]);
        }

        if (method_exists($subject, 'restore')) {
            $subject->restore();
        }

        if ($subject instanceof Quote || $subject instanceof Contract) {
            $subject->document()->withTrashed()->first()?->restore();
        }
    }

    /**
     * @param  Collection<int, Activity>  $activities
     * @return array{sizes: array<string, int>, revertedBy: array<int, array{id: int, name: ?string}>, revertsOf: array<int, int>}
     */
    private function links(Collection $activities): array
    {
        $tenantId = current_tenant_id();
        $batchUuids = $activities->pluck('batch_uuid')->filter()->unique()->values()->all();
        $revertUuids = $activities->filter(fn (Activity $a): bool => $a->reverted_at !== null)->pluck('revert_batch_uuid')->filter()->unique()->values()->all();

        /** @var array<string, int> $sizes */
        $sizes = $batchUuids === [] ? [] : Activity::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('batch_uuid', $batchUuids)
            ->selectRaw('batch_uuid, count(*) as aggregate')
            ->groupBy('batch_uuid')
            ->pluck('aggregate', 'batch_uuid')
            ->map(fn (mixed $count): int => is_numeric($count) ? (int) $count : 0)
            ->all();

        $revertRows = $revertUuids === [] ? collect() : Activity::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('batch_uuid', $revertUuids)
            ->with('causer')
            ->orderBy('id')
            ->get()
            ->groupBy('batch_uuid');

        $originals = $batchUuids === [] ? collect() : Activity::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('revert_batch_uuid', $batchUuids)
            ->orderBy('id')
            ->get(['id', 'revert_batch_uuid', 'subject_type', 'subject_id'])
            ->groupBy('revert_batch_uuid');

        $revertedBy = [];
        $revertsOf = [];

        foreach ($activities as $activity) {
            if ($activity->reverted_at !== null) {
                /** @var Collection<int, Activity> $rows */
                $rows = $revertRows->get($activity->revert_batch_uuid, collect());
                $row = $this->closest($rows, $activity);

                if ($row !== null) {
                    $revertedBy[$activity->id] = ['id' => $row->id, 'name' => $row->causerUser()?->name];
                }
            }

            if ($activity->batch_uuid !== null) {
                /** @var Collection<int, Activity> $rows */
                $rows = $originals->get($activity->batch_uuid, collect());
                $row = $this->closest($rows, $activity);

                if ($row !== null) {
                    $revertsOf[$activity->id] = $row->id;
                }
            }
        }

        return ['sizes' => $sizes, 'revertedBy' => $revertedBy, 'revertsOf' => $revertsOf];
    }

    /** @param  Collection<int, Activity>  $rows */
    private function closest(Collection $rows, Activity $activity): ?Activity
    {
        return $rows->first(fn (Activity $r): bool => $r->subject_type === $activity->subject_type && $r->subject_id === $activity->subject_id)
            ?? $rows->first();
    }

    /**
     * @param  Verdict  $verdict
     * @param  array{sizes: array<string, int>, revertedBy: array<int, array{id: int, name: ?string}>, revertsOf: array<int, int>}  $links
     */
    private function data(Activity $activity, array $verdict, int $size, array $links): ActivityRevertData
    {
        [$reason, $blocking] = $verdict;
        $status = match (true) {
            $reason === Reason::AlreadyReverted => ActivityRevertStatusEnum::Reverted,
            $reason === null => ActivityRevertStatusEnum::Available,
            default => ActivityRevertStatusEnum::Unavailable,
        };

        return new ActivityRevertData(
            status: $status,
            reason: $reason,
            changes_count: count($this->loggedKeys($activity)),
            batch_size: max($size, 1),
            blocking_activity_id: $blocking,
            reverted_at: $activity->reverted_at?->toIso8601String(),
            reverted_by_name: $links['revertedBy'][$activity->id]['name'] ?? null,
            reverted_by_activity_id: $links['revertedBy'][$activity->id]['id'] ?? null,
            reverts_activity_id: $links['revertsOf'][$activity->id] ?? null,
        );
    }

    private function label(Activity $activity): ?string
    {
        $subject = $activity->subject;

        $label = match (true) {
            $subject instanceof Client, $subject instanceof Contact, $subject instanceof CleaningObject,
            $subject instanceof Tenant => $subject->name,
            $subject instanceof Task => $subject->title,
            $subject instanceof Expense => $subject->supplier_name,
            $subject instanceof TenantInterface => __('app.audit_subject_invoice_settings'),
            $subject instanceof Invoice, $subject instanceof Quote, $subject instanceof Contract => $subject->number ?? __('app.invoice_status_draft'),
            default => null,
        };

        return is_string($label) ? $label : null;
    }

    /** @param  Collection<int, Activity>  $activities */
    private function attachSubjects(Collection $activities, bool $lock = false): void
    {
        $tenantId = current_tenant_id();
        $pending = $activities->reject(fn (Activity $a): bool => $a->relationLoaded('subject'));
        $idsByClass = [];

        foreach ($pending as $activity) {
            $class = $this->modelClass($activity);

            if ($class !== null) {
                $idsByClass[$class][] = $this->scalar($activity->subject_id);
            }
        }

        /** @var array<class-string<Model>, Collection<string, Model>> $loaded */
        $loaded = [];

        foreach ($idsByClass as $class => $ids) {
            $loaded[$class] = $this->query($class)->whereKey(array_unique($ids))->when($lock, fn (Builder $q) => $q->lockForUpdate())->get()->keyBy(fn (Model $m): string => $this->scalar($m->getKey()));
        }

        foreach ($pending as $activity) {
            $class = $this->modelClass($activity);
            $subject = $class !== null ? ($loaded[$class][$this->scalar($activity->subject_id)] ?? null) : null;
            $visible = $subject !== null && $activity->tenant_id === $tenantId && $this->belongsToTenant($subject, $tenantId);
            $activity->setRelation('subject', $visible ? $subject : null);
        }

        $this->attachParents($activities);
    }

    /** @param  Collection<int, Activity>  $activities */
    private function attachParents(Collection $activities): void
    {
        $subjects = $activities->map(fn (Activity $a): mixed => $a->subject)->filter(fn (mixed $s): bool => $s instanceof Model);

        $clientAlias = Relation::getMorphAlias(Client::class);
        $objectAlias = Relation::getMorphAlias(CleaningObject::class);

        /** @var list<array{string, class-string<Model>, string, Closure(Model): bool}> $maps */
        $maps = [
            ['client', Client::class, 'client_id', fn (Model $s): bool => $s instanceof CleaningObject],
            ['contactable', Client::class, 'contactable_id', fn (Model $s): bool => $s instanceof Contact && $s->contactable_type === $clientAlias],
            ['contactable', CleaningObject::class, 'contactable_id', fn (Model $s): bool => $s instanceof Contact && $s->contactable_type === $objectAlias],
        ];

        foreach ($maps as [$relation, $parentClass, $foreignKey, $isChild]) {
            $children = $subjects->filter(fn (mixed $s): bool => $isChild($s) && ! $s->relationLoaded($relation));

            if ($children->isEmpty()) {
                continue;
            }

            $parents = $this->query($parentClass)
                ->whereKey($children->map(fn (Model $c): mixed => $c->getAttribute($foreignKey))->unique()->values()->all())
                ->get()
                ->keyBy(fn (Model $m): string => $this->scalar($m->getKey()));

            $children->each(fn (Model $c) => $c->setRelation($relation, $parents->get($this->scalar($c->getAttribute($foreignKey)))));
        }
    }

    /**
     * @param  class-string<Model>  $class
     * @return Builder<Model>
     */
    private function query(string $class): Builder
    {
        return $class::query()->withoutGlobalScope(SoftDeletingScope::class);
    }

    /** @return array<string, mixed> */
    private function currentValues(Model $subject): array
    {
        $extract = $subject::class.'::extractChanges';

        if (! is_callable($extract)) {
            return [];
        }

        /** @var array<string, mixed> */
        return $extract($subject);
    }

    private function key(string $class, mixed $id): string
    {
        return $class.'|'.$this->scalar($id);
    }

    private function scalar(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }

    private function same(mixed $a, mixed $b): bool
    {
        return json_encode($a) === json_encode($b);
    }

    private function isMasked(Model $model, string $field): bool
    {
        $cast = $model->getCasts()[$field] ?? null;

        return in_array($field, $model->getHidden(), true)
            || (is_string($cast) && str_starts_with($cast, 'encrypted'));
    }
}
