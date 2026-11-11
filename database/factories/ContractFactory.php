<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ContractableTypeEnum;
use App\Enums\ContractCategoryEnum;
use App\Enums\ContractSourceEnum;
use App\Enums\ContractStatusEnum;
use App\Enums\ContractTermTypeEnum;
use App\Enums\DocumentTypeEnum;
use App\Models\CleaningObject;
use App\Models\Client;
use App\Models\Contract;
use App\Models\Document;
use App\Models\Quote;
use App\Models\TenantMembership;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Contract>
 */
final class ContractFactory extends Factory
{
    /** @var class-string<Contract> */
    protected $model = Contract::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'contractable_type' => ContractableTypeEnum::CleaningObject->value,
            // Closure (not a bare `Factory` instance) so the tenant override passed to
            // `Contract::factory()->create(['tenant_id' => $tenant->id])` propagates into
            // the nested `CleaningObject` — a plain `CleaningObject::factory()` value would
            // resolve independently of this model's own `tenant_id` override.
            'contractable_id' => function (array $attributes): string {
                $overrides = array_key_exists('tenant_id', $attributes) && $attributes['tenant_id'] !== null
                    ? ['tenant_id' => $attributes['tenant_id']]
                    : [];

                return CleaningObject::factory()->create($overrides)->id;
            },
            'category' => ContractCategoryEnum::ServiceAgreement,
            'status' => ContractStatusEnum::Draft,
            'term_type' => ContractTermTypeEnum::Fixed,
            'document_body' => '<p>Zmluvné podmienky.</p>',
            'title' => 'Zmluva o vykonaní upratovacích prác',
            'number' => null,
            'valid_from' => now()->toDateString(),
            'end_date' => now()->addYear()->toDateString(),
            'signed_at' => null,
            'is_signed' => false,
            'source' => ContractSourceEnum::Generated,
            'terminated_at' => null,
            'termination_reason' => null,
            'notes' => null,
        ];
    }

    public function configure(): static
    {
        return $this
            ->afterMaking(function (Contract $contract): void {
                $contract->setRelation('pendingBody', $contract->getAttribute('document_body'));
                $contract->offsetUnset('document_body');
            })
            ->afterCreating(function (Contract $contract): void {
                $body = $contract->getRelation('pendingBody');
                $contract->unsetRelation('pendingBody');

                if ($contract->document()->exists()) {
                    return;
                }

                Document::create([
                    'tenant_id' => $contract->tenant_id,
                    'documentable_type' => $contract->getMorphClass(),
                    'documentable_id' => $contract->id,
                    'type' => DocumentTypeEnum::fromCategory($contract->category),
                    'title' => $contract->title,
                    'body' => is_string($body) ? $body : '<p>Zmluvné podmienky.</p>',
                ]);
            });
    }

    public function withBody(string $html): static
    {
        return $this->state(fn () => ['document_body' => $html]);
    }

    public function draft(): static
    {
        return $this->state(fn () => [
            'status' => ContractStatusEnum::Draft,
            'signed_at' => null,
            'is_signed' => false,
        ]);
    }

    public function active(): static
    {
        return $this->state(fn () => [
            'status' => ContractStatusEnum::Active,
            'signed_at' => now()->subDay(),
            'is_signed' => true,
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn () => [
            'status' => ContractStatusEnum::Expired,
            'term_type' => ContractTermTypeEnum::Fixed,
            'signed_at' => now()->subYear(),
            'is_signed' => true,
            'valid_from' => now()->subYear()->toDateString(),
            'end_date' => now()->subDay()->toDateString(),
        ]);
    }

    public function terminated(): static
    {
        return $this->state(fn () => [
            'status' => ContractStatusEnum::Terminated,
            'signed_at' => now()->subMonths(2),
            'is_signed' => true,
            'terminated_at' => now(),
            'termination_reason' => 'Ukončené dohodou.',
        ]);
    }

    public function indefinite(): static
    {
        return $this->state(fn () => [
            'term_type' => ContractTermTypeEnum::Indefinite,
            'end_date' => null,
        ]);
    }

    public function forObject(CleaningObject $object): static
    {
        return $this->state(fn () => [
            'contractable_type' => ContractableTypeEnum::CleaningObject->value,
            'contractable_id' => $object->id,
            'category' => ContractCategoryEnum::ServiceAgreement,
        ]);
    }

    /**
     * @param  TenantMembership|Factory<TenantMembership>  $membership
     */
    public function forMembership(TenantMembership|Factory $membership): static
    {
        return $this->state(fn () => [
            'contractable_type' => ContractableTypeEnum::TenantMembership->value,
            'contractable_id' => $membership instanceof TenantMembership ? $membership->id : $membership,
            'category' => ContractCategoryEnum::Employment,
        ]);
    }

    public function forClient(Client $client): static
    {
        return $this->state(fn () => [
            'contractable_type' => ContractableTypeEnum::Client->value,
            'contractable_id' => $client->id,
            'category' => ContractCategoryEnum::ServiceAgreement,
        ]);
    }

    public function fromQuote(Quote $quote): static
    {
        return $this->state(fn () => [
            'quote_id' => $quote->id,
            'contractable_type' => ContractableTypeEnum::CleaningObject->value,
            'contractable_id' => $quote->cleaning_object_id,
            'category' => ContractCategoryEnum::ServiceAgreement,
            'title' => $quote->subject ?? __('app.contract_default_title_from_quote'),
            'number' => $quote->number,
        ]);
    }

    public function fromFile(): static
    {
        return $this->state(fn () => [
            'source' => ContractSourceEnum::File,
            'status' => ContractStatusEnum::Active,
            'is_signed' => true,
            'signed_at' => null,
            'file_sha256' => hash('sha256', Str::uuid()->toString()),
        ])->afterCreating(function (Contract $contract): void {
            $contract->document()->update(['body' => null, 'show_header' => false, 'show_footer' => false]);
        });
    }
}
