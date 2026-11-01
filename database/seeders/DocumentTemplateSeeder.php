<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\DocumentTypeEnum;
use App\Enums\EmploymentContractTypeEnum;
use App\Models\DocumentTemplate;
use Illuminate\Database\Seeder;

final class DocumentTemplateSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->definitions() as [$name, $type, $employmentType, $body]) {
            DocumentTemplate::query()->withoutGlobalScopes()->firstOrCreate(
                ['tenant_id' => null, 'type' => $type->value, 'employment_type' => $employmentType?->value, 'name' => $name],
                ['body' => $body, 'show_header' => true, 'show_footer' => true, 'is_active' => true],
            );
        }
    }

    /** @return list<array{string, DocumentTypeEnum, EmploymentContractTypeEnum|null, string}> */
    private function definitions(): array
    {
        $heading = static fn (string $title): string => '<h1 style="text-align: center;">'.$title.'</h1>';
        $parties = '<p>{{block.parties}}</p>';
        $signatures = '<p>{{block.signatures}}</p>';

        return [
            [
                'Zmluva o poskytovaní upratovacích služieb',
                DocumentTypeEnum::ServiceAgreement,
                null,
                $heading('{{contract.title}}').$parties.'<h2>Predmet zmluvy</h2><p>{{object.name}}, {{object.address}}</p><h2>Platnosť</h2><p>{{if.fixed_term}}Zmluva sa uzatvára na dobu určitú do {{contract.end_date}}.</p><p>{{if.indefinite_term}}Zmluva sa uzatvára na dobu neurčitú.</p>'.$signatures,
            ],
            [
                'Pracovná zmluva (TPP)',
                DocumentTypeEnum::Employment,
                EmploymentContractTypeEnum::Tpp,
                $heading('{{contract.title}}').$parties.'<p>{{block.employment_terms}}</p><p>{{if.probation}}Skúšobná doba trvá do {{employment.probation_end_date}}.</p>'.$signatures,
            ],
            [
                'Dohoda o vykonaní práce (DPP)',
                DocumentTypeEnum::Employment,
                EmploymentContractTypeEnum::Dpp,
                $heading('{{contract.title}}').$parties.'<p>{{block.employment_terms}}</p>'.$signatures,
            ],
            [
                'Dohoda o pracovnej činnosti (DPČ)',
                DocumentTypeEnum::Employment,
                EmploymentContractTypeEnum::Dpc,
                $heading('{{contract.title}}').$parties.'<p>{{block.employment_terms}}</p>'.$signatures,
            ],
            [
                'Zmluva so živnostníkom',
                DocumentTypeEnum::Employment,
                EmploymentContractTypeEnum::SelfEmployed,
                $heading('{{contract.title}}').$parties.'<p>{{block.employment_terms}}</p><p>IČO: {{employee.ico}}</p>'.$signatures,
            ],
            [
                'Dohoda o mlčanlivosti',
                DocumentTypeEnum::Nda,
                null,
                $heading('{{contract.title}}').$parties.$signatures,
            ],
            [
                'Súhlas so spracovaním osobných údajov',
                DocumentTypeEnum::Gdpr,
                null,
                $heading('{{contract.title}}').$parties.$signatures,
            ],
            [
                'Cenová ponuka',
                DocumentTypeEnum::Quote,
                null,
                $heading('Cenová ponuka {{quote.number}}').'<p>{{quote.subject}}</p><p>{{block.quote_items}}</p><p>{{block.quote_totals}}</p><p>Platnosť ponuky do {{quote.valid_until}}.</p><p>{{quote.note}}</p>'.$signatures,
            ],
        ];
    }
}
