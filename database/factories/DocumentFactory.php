<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\DocumentTypeEnum;
use App\Models\Document;
use App\Models\TenantMembership;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Document>
 */
final class DocumentFactory extends Factory
{
    /** @var class-string<Document> */
    protected $model = Document::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'type' => DocumentTypeEnum::EmployeeIdCard,
            'title' => 'Doklad',
            'body' => null,
            'show_header' => false,
            'show_footer' => false,
        ];
    }

    public function forEmployee(TenantMembership $membership): static
    {
        return $this->state(fn () => [
            'tenant_id' => $membership->tenant_id,
            'documentable_type' => $membership->getMorphClass(),
            'documentable_id' => $membership->id,
        ]);
    }
}
