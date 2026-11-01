<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\Contracts\ContractGenerateData;
use App\Data\Contracts\ContractListItemData;
use App\Data\Contracts\ContractTerminateData;
use App\Data\Contracts\ContractUpsertData;
use App\Data\Documents\DocumentRenderData;
use App\Enums\ContractableTypeEnum;
use App\Enums\ContractCategoryEnum;
use App\Enums\ContractStatusEnum;
use App\Enums\DocumentTypeEnum;
use App\Enums\PermissionEnum;
use App\Events\ContractSigned;
use App\Models\CleaningObject;
use App\Models\Client;
use App\Models\Contract;
use App\Models\Document;
use App\Models\DocumentTemplate;
use App\Models\EmploymentContract;
use App\Models\Quote;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use App\Services\Documents\ContractTextService;
use App\Services\Documents\DocumentContextBuilder;
use App\Services\Documents\DocumentFrame;
use App\Services\Documents\DocumentHtmlSanitizer;
use App\Services\Documents\DocumentPdfRenderer;
use App\Services\Documents\DocumentTemplateCatalogue;
use App\Services\Documents\DocumentTemplateRenderer;
use App\Utils\AllowedFilter;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;
use Spatie\QueryBuilder\QueryBuilder;

final readonly class ContractService
{
    public function __construct(
        private DatabaseManager $db,
        private ContractTextService $text,
        private DocumentTemplateRenderer $renderer,
        private DocumentHtmlSanitizer $sanitizer,
        private DocumentContextBuilder $contexts,
        private DocumentTemplateCatalogue $catalogue,
        private DocumentPdfRenderer $pdf,
    ) {}

    /**
     * @return LengthAwarePaginator<int, ContractListItemData>
     */
    public function paginate(Request $request, User $actor): LengthAwarePaginator
    {
        return QueryBuilder::for(Contract::query())
            ->allowedFilters(
                AllowedFilter::search(['title', 'number']),
                AllowedFilter::dynamic('status'),
                AllowedFilter::dynamic('category'),
                AllowedFilter::dynamic('term_type'),
                AllowedFilter::dynamic('contractable_type'),
                AllowedFilter::dynamic('valid_from')->date(),
                AllowedFilter::dynamic('end_date')->date(),
            )
            ->allowedSorts('title', 'number', 'status', 'category', 'valid_from', 'end_date', 'created_at')
            ->defaultSort('-created_at')
            ->with(['document', 'contractable' => function (MorphTo|Relation $relation) {
                /** @var MorphTo<Model, Contract> $relation */
                return $relation->morphWith([
                    TenantMembership::class => ['user:id,name,email'],
                ]);
            }])
            ->paginate($request->integer('per_page', 25))
            ->withQueryString()
            ->through(fn (Contract $contract) => ContractListItemData::fromModel($contract, $this->text, $actor));
    }

    /** @return list<ContractListItemData>|null  null without view permission */
    public function listFor(CleaningObject|Client|TenantMembership $subject, User $actor): ?array
    {
        if (! $actor->can(PermissionEnum::ViewContracts->value)) {
            return null;
        }

        $query = Contract::query()->with(['document', 'contractable']);

        if ($subject instanceof Client) {
            $objectIds = $subject->objects()->pluck('id');

            $query->where(function (Builder $q) use ($subject, $objectIds): void {
                $q->where(fn (Builder $inner) => $inner
                    ->where('contractable_type', (new Client)->getMorphClass())
                    ->where('contractable_id', $subject->id))
                    ->orWhere(fn (Builder $inner) => $inner
                        ->where('contractable_type', (new CleaningObject)->getMorphClass())
                        ->whereIn('contractable_id', $objectIds));
            });
        } else {
            $query->where('contractable_type', $subject->getMorphClass())->where('contractable_id', $subject->id);
        }

        return array_values($query->latest()->limit(50)->get()
            ->map(fn (Contract $contract) => ContractListItemData::fromModel($contract, $this->text, $actor))
            ->all());
    }

    public function canCreateFor(CleaningObject|Client|TenantMembership $subject, User $actor): bool
    {
        if (! $actor->can(PermissionEnum::CreateContracts->value)) {
            return false;
        }

        return ! $subject instanceof TenantMembership || $actor->can(PermissionEnum::ViewEmployeePersonalData->value);
    }

    public function generate(ContractGenerateData $data, User $actor): DocumentRenderData
    {
        $subject = $this->resolveSubject($data->contractable_type, $data->contractable_id);
        $this->assertCategoryMatchesContractable($data->category, $data->contractable_type);
        $this->assertActorMaySee($subject, $actor);

        $tenant = Tenant::query()->findOrFail(current_tenant_id());
        $html = $this->buildHtml($data, $subject, $tenant);
        $type = DocumentTypeEnum::fromCategory($data->category);
        $unknown = $this->renderer->violations($type, $html);

        return new DocumentRenderData(
            html: $html,
            missing_tokens: array_values(array_diff($this->renderer->unresolvedTokens($html), $unknown)),
            unknown_tokens: $unknown,
            letterhead: $data->show_header ? DocumentFrame::letterhead($tenant) : null,
            footer: $data->show_footer ? DocumentFrame::footer($tenant) : [],
        );
    }

    public function generatePdf(ContractGenerateData $data, User $actor): string
    {
        $subject = $this->resolveSubject($data->contractable_type, $data->contractable_id);
        $this->assertCategoryMatchesContractable($data->category, $data->contractable_type);
        $this->assertActorMaySee($subject, $actor);

        $tenant = Tenant::query()->findOrFail(current_tenant_id());

        return $this->pdf->render(
            $data->title ?? __('app.contract_pdf_title_fallback'),
            $this->buildHtml($data, $subject, $tenant),
            $tenant,
            DocumentTypeEnum::fromCategory($data->category),
            $data->show_header,
            $data->show_footer,
        );
    }

    public function create(ContractUpsertData $data, User $actor, ?Quote $sourceQuote = null): Contract
    {
        $subject = $this->resolveSubject($data->contractable_type, $data->contractable_id);
        $this->assertCategoryMatchesContractable($data->category, $data->contractable_type);
        $this->assertActorMaySee($subject, $actor);

        return $this->db->transaction(function () use ($data, $subject, $sourceQuote): Contract {
            $tenant = Tenant::query()->findOrFail(current_tenant_id());
            $body = $this->resolveForSave($data, $subject, $tenant, $sourceQuote);

            $contract = Contract::create([
                'document_template_id' => $data->document_template_id,
                'quote_id' => $sourceQuote?->id,
                'contractable_type' => $data->contractable_type->value,
                'contractable_id' => $data->contractable_id,
                'category' => $data->category,
                'status' => ContractStatusEnum::Draft,
                'term_type' => $data->term_type,
                'title' => $data->title,
                'number' => $data->number,
                'valid_from' => $data->valid_from,
                'end_date' => $data->end_date,
                'notes' => $data->notes,
            ]);

            Document::create([
                'documentable_type' => $contract->getMorphClass(),
                'documentable_id' => $contract->id,
                'document_template_id' => $data->document_template_id,
                'type' => DocumentTypeEnum::fromCategory($data->category),
                'title' => $data->title,
                'body' => $body,
                'show_header' => $data->show_header,
                'show_footer' => $data->show_footer,
            ]);

            if ($data->employment !== null) {
                EmploymentContract::create([
                    'tenant_id' => $contract->tenant_id,
                    'contract_id' => $contract->id,
                    'employment_type' => $data->employment->employment_type,
                    'position' => $data->employment->position,
                    'hourly_rate' => $data->employment->hourly_rate,
                    'monthly_salary' => $data->employment->monthly_salary,
                    'weekly_hours' => $data->employment->weekly_hours,
                    'probation_end_date' => $data->employment->probation_end_date,
                ]);
            }

            return $contract->load(['employmentContract', 'documentTemplate', 'document']);
        });
    }

    public function update(Contract $contract, ContractUpsertData $data, User $actor): Contract
    {
        if (! $contract->isEditable()) {
            throw ValidationException::withMessages(['status' => [__('app.contract_not_editable')]]);
        }

        $subject = $this->resolveSubject($data->contractable_type, $data->contractable_id);
        $this->assertCategoryMatchesContractable($data->category, $data->contractable_type);
        $this->assertActorMaySee($subject, $actor);

        if ($contract->contractable_type === ContractableTypeEnum::TenantMembership->value) {
            $this->assertActorMaySeeContract($contract, $actor);
        }

        return $this->db->transaction(function () use ($contract, $data, $subject): Contract {
            $tenant = Tenant::withoutGlobalScopes()->findOrFail($contract->tenant_id);
            $body = $this->resolveForSave($data, $subject, $tenant, $contract->quote);

            $contract->update([
                'document_template_id' => $data->document_template_id,
                'contractable_type' => $data->contractable_type->value,
                'contractable_id' => $data->contractable_id,
                'category' => $data->category,
                'term_type' => $data->term_type,
                'title' => $data->title,
                'number' => $data->number,
                'valid_from' => $data->valid_from,
                'end_date' => $data->end_date,
                'notes' => $data->notes,
            ]);

            Document::query()->updateOrCreate(
                ['documentable_type' => $contract->getMorphClass(), 'documentable_id' => $contract->id],
                [
                    'tenant_id' => $contract->tenant_id,
                    'document_template_id' => $data->document_template_id,
                    'type' => DocumentTypeEnum::fromCategory($data->category),
                    'title' => $data->title,
                    'body' => $body,
                    'show_header' => $data->show_header,
                    'show_footer' => $data->show_footer,
                ],
            );

            if ($data->employment !== null) {
                $contract->employmentContract()->updateOrCreate([], [
                    'tenant_id' => $contract->tenant_id,
                    'employment_type' => $data->employment->employment_type,
                    'position' => $data->employment->position,
                    'hourly_rate' => $data->employment->hourly_rate,
                    'monthly_salary' => $data->employment->monthly_salary,
                    'weekly_hours' => $data->employment->weekly_hours,
                    'probation_end_date' => $data->employment->probation_end_date,
                ]);
            } else {
                $contract->employmentContract()->delete();
            }

            return $contract->load(['employmentContract', 'documentTemplate', 'document']);
        });
    }

    public function sign(Contract $contract): Contract
    {
        if (! $contract->canBeSigned()) {
            throw ValidationException::withMessages(['status' => [__('app.contract_cannot_sign')]]);
        }

        $missing = $this->text->missingTokens($contract);

        if ($missing !== []) {
            throw ValidationException::withMessages([
                'status' => [__('app.contract_unresolved_tokens', ['tokens' => $this->catalogue->labelsOf($missing)])],
            ]);
        }

        return $this->db->transaction(function () use ($contract): Contract {
            $this->text->freezeBlocks($contract);
            $contract->update(['status' => ContractStatusEnum::Active, 'signed_at' => now()]);

            ContractSigned::dispatch($contract->tenant_id, $contract->id);

            return $contract;
        });
    }

    public function terminate(Contract $contract, ContractTerminateData $data): Contract
    {
        if (! $contract->canBeTerminated()) {
            throw ValidationException::withMessages(['status' => [__('app.contract_cannot_terminate')]]);
        }

        return $this->db->transaction(function () use ($contract, $data): Contract {
            $contract->update([
                'status' => ContractStatusEnum::Terminated,
                'terminated_at' => $data->terminated_at,
                'termination_reason' => $data->termination_reason,
            ]);

            return $contract;
        });
    }

    public function delete(Contract $contract): void
    {
        if (! $contract->isEditable()) {
            throw ValidationException::withMessages(['status' => [__('app.contract_not_editable')]]);
        }

        $this->db->transaction(function () use ($contract): void {
            $contract->document?->delete();
            $contract->delete();
        });
    }

    private function buildHtml(ContractGenerateData $data, CleaningObject|Client|TenantMembership $subject, Tenant $tenant): string
    {
        $quote = $data->quote_id !== null ? Quote::query()->find($data->quote_id) : null;
        $type = DocumentTypeEnum::fromCategory($data->category);

        $raw = $data->body;

        if ($raw === null && $data->document_template_id !== null) {
            $raw = DocumentTemplate::query()->find($data->document_template_id)?->body;
        }

        $raw ??= $this->catalogue->skeleton($type, $quote !== null);

        return $this->renderer->render(
            $this->sanitizer->sanitize($raw),
            $this->contexts->forContract($subject, $tenant, $data, $quote),
        );
    }

    private function resolveForSave(ContractUpsertData $data, CleaningObject|Client|TenantMembership $subject, Tenant $tenant, ?Quote $quote): string
    {
        $context = $this->contexts->forContract($subject, $tenant, $data->terms($quote?->id), $quote);

        return $this->sanitizer->sanitize(
            $this->renderer->render($this->sanitizer->sanitize($data->body), $context),
        );
    }

    private function resolveSubject(ContractableTypeEnum $type, string $id): CleaningObject|Client|TenantMembership
    {
        return match ($type) {
            ContractableTypeEnum::CleaningObject => CleaningObject::query()->findOrFail($id),
            ContractableTypeEnum::Client => Client::query()->findOrFail($id),
            ContractableTypeEnum::TenantMembership => TenantMembership::query()
                ->where('tenant_id', current_tenant_id())
                ->findOrFail($id),
        };
    }

    private function assertCategoryMatchesContractable(ContractCategoryEnum $category, ContractableTypeEnum $contractableType): void
    {
        if (! in_array($contractableType, $category->allowedContractableTypes(), true)) {
            throw ValidationException::withMessages([
                'contractable_type' => [__('app.contract_category_contractable_mismatch')],
            ]);
        }
    }

    private function assertActorMaySee(CleaningObject|Client|TenantMembership $subject, User $actor): void
    {
        if ($subject instanceof TenantMembership && ! $actor->can(PermissionEnum::ViewEmployeePersonalData->value)) {
            throw new AuthorizationException(__('app.employee_personal_data_forbidden'));
        }
    }

    private function assertActorMaySeeContract(Contract $contract, User $actor): void
    {
        if (! $this->text->canReadText($contract, $actor)) {
            throw new AuthorizationException(__('app.employee_personal_data_forbidden'));
        }
    }
}
