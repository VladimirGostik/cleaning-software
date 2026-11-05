<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Enums\ObjectTypeEnum;
use App\Enums\RecurringFrequencyEnum;
use App\Services\LegacyImport\LegacyRecordMapper;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class LegacyRecordMapperTest extends TestCase
{
    private LegacyRecordMapper $mapper;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mapper = new LegacyRecordMapper;
    }

    /**
     * @param  array<string, mixed>  $headers
     * @return array<string, mixed>
     */
    private function template(int $id, string $name, array $headers = [], int $firm = 2, int $client = 1): array
    {
        return ['id' => $id, 'firm' => $firm, 'client' => $client, 'name' => $name, 'header1' => null, 'header2' => null, 'header3' => null, 'header4' => null, ...$headers];
    }

    public function test_firm_input_normalises_and_register_wins(): void
    {
        $input = $this->mapper->firmInput([
            'id' => 2,
            'company_name' => 'Legacy Name',
            'company_address' => 'Legacy 1',
            'postal_code' => '81101',
            'city' => 'Legacy City',
            'company_ico' => '11 111 111',
            'company_dic' => '20 20 20 20 20',
            'company_iban' => 'sk89 7500 0000 0001 2345 6789',
            'register' => ['name' => 'Register Name s.r.o.', 'ico' => '22 222 222', 'street' => 'Register 5', 'city' => 'Register City', 'postal_code' => '90201'],
        ]);

        $this->assertSame('Register Name s.r.o.', $input['name']);
        $this->assertSame('22222222', $input['ico']);
        $this->assertSame('Register 5', $input['address_line']);
        $this->assertSame('Register City', $input['city']);
        $this->assertSame('902 01', $input['postal_code']);
        $this->assertSame('2020202020', $input['dic']);
        $this->assertSame('SK8975000000000123456789', $input['iban']);
        $this->assertFalse($input['is_vat_payer']);
        $this->assertNull($input['vat_number']);
        $this->assertSame('SK', $input['country']);
    }

    public function test_firm_input_falls_back_to_legacy_without_register(): void
    {
        $input = $this->mapper->firmInput([
            'id' => 23, 'company_name' => ' Legacy Name ', 'company_address' => 'Legacy 1', 'postal_code' => '811 01',
            'city' => 'Legacy City', 'company_ico' => '44 444 444', 'company_dic' => null, 'company_iban' => null,
        ]);

        $this->assertSame('Legacy Name', $input['name']);
        $this->assertSame('44444444', $input['ico']);
        $this->assertSame('811 01', $input['postal_code']);
        $this->assertNull($input['dic']);
        $this->assertNull($input['iban']);
    }

    public function test_blank_register_value_falls_back_to_legacy(): void
    {
        $input = $this->mapper->firmInput([
            'id' => 2, 'company_name' => 'Legacy Name', 'company_address' => null, 'postal_code' => null, 'city' => 'Legacy City',
            'company_ico' => '11111111', 'company_dic' => null, 'company_iban' => null,
            'register' => ['name' => '  ', 'ico' => '11111111', 'street' => '', 'city' => '', 'postal_code' => ''],
        ]);

        $this->assertSame('Legacy Name', $input['name']);
        $this->assertSame('Legacy City', $input['city']);
        $this->assertNull($input['address_line']);
    }

    public function test_client_type_follows_resolved_ico(): void
    {
        $corporate = $this->mapper->clientInput(['id' => 1, 'company_name' => 'Legacy', 'company_ico' => null, 'register' => ['name' => 'Reg', 'ico' => '33 333 333']], []);
        $private = $this->mapper->clientInput(['id' => 2, 'company_name' => 'Person', 'company_ico' => ' ', 'company_dic' => null], []);

        $this->assertSame('corporate', $corporate['type']);
        $this->assertSame('33333333', $corporate['ico']);
        $this->assertSame('Reg', $corporate['name']);
        $this->assertSame('private', $private['type']);
        $this->assertNull($private['ico']);
    }

    public function test_client_vat_payer_only_with_vat_number(): void
    {
        $with = $this->mapper->clientInput(['id' => 7, 'company_name' => 'A', 'company_ico' => '1'], ['7' => 'SK 202 020']);
        $without = $this->mapper->clientInput(['id' => 8, 'company_name' => 'B', 'company_ico' => '2'], ['8' => null]);

        $this->assertSame('SK202020', $with['vat_number']);
        $this->assertTrue($with['is_vat_payer']);
        $this->assertNull($without['vat_number']);
        $this->assertFalse($without['is_vat_payer']);
    }

    /** @return array<string, array{string, string}> */
    public static function streetCases(): array
    {
        return [
            'single number' => ['Hlavna č.13', 'Hlavna 13'],
            'comma' => ['Hlavna č.13,15', 'Hlavna 13,15'],
            'range' => ['Hlavna č.13-15', 'Hlavna 13-15'],
            'letter' => ['Hlavna č.13a', 'Hlavna 13a'],
            'spaced' => ['Hlavna  č. 13', 'Hlavna 13'],
        ];
    }

    #[DataProvider('streetCases')]
    public function test_street_derivation(string $name, string $street): void
    {
        $object = $this->mapper->objects([$this->template(1, $name)])[0];

        $this->assertSame($street, $object['street']);
        $this->assertSame(ObjectTypeEnum::ResidentialBuilding, $object['type']);
    }

    public function test_name_without_house_number_is_office_without_street(): void
    {
        $object = $this->mapper->objects([$this->template(1, 'Kancelária Test')])[0];

        $this->assertSame(ObjectTypeEnum::Office, $object['type']);
        $this->assertNull($object['street']);
    }

    /** @return array<string, array{string}> */
    public static function standSuffixes(): array
    {
        return ['compact' => ['kont.stojisko'], 'spaced' => ['kont. stojisko'], 'upper' => ['KONT.STOJISKO'], 'title' => ['Kont. Stojisko']];
    }

    #[DataProvider('standSuffixes')]
    public function test_container_stand_merges_into_base(string $suffix): void
    {
        $objects = $this->mapper->objects([
            $this->template(1, 'Hlavna č.1'),
            $this->template(2, 'Hlavna č.1 '.$suffix),
        ]);

        $this->assertCount(1, $objects);
        $this->assertSame([2], $objects[0]['merged_template_ids']);
        $this->assertSame(ObjectTypeEnum::ResidentialBuilding, $objects[0]['type']);
    }

    public function test_container_stand_without_base_stays_standalone_common_areas(): void
    {
        $objects = $this->mapper->objects([$this->template(2, 'Hlavna č.1 kont.stojisko')]);

        $this->assertCount(1, $objects);
        $this->assertSame('Hlavna č.1 kont.stojisko', $objects[0]['name']);
        $this->assertSame(ObjectTypeEnum::CommonAreas, $objects[0]['type']);
        $this->assertSame('Hlavna 1', $objects[0]['street']);
    }

    public function test_container_stand_does_not_merge_across_clients(): void
    {
        $objects = $this->mapper->objects([
            $this->template(1, 'Hlavna č.1', client: 1),
            $this->template(2, 'Hlavna č.1 kont.stojisko', client: 2),
        ]);

        $this->assertCount(2, $objects);
    }

    public function test_billing_name_prefers_header1_with_lowercase_continuation(): void
    {
        $object = $this->mapper->objects([$this->template(1, 'Hlavna č.1', ['header1' => 'Spoločenstvo vlastníkov', 'header2' => 'bytov Hlavna 1', 'header4' => 'Other'])])[0];

        $this->assertSame('Spoločenstvo vlastníkov bytov Hlavna 1', $object['billing_name']);
    }

    public function test_billing_name_ignores_representation_and_uppercase_next_line(): void
    {
        $representation = $this->mapper->objects([$this->template(1, 'A č.1', ['header1' => 'Owner', 'header2' => 'v zastúpení Manager'])])[0];
        $uppercase = $this->mapper->objects([$this->template(2, 'B č.2', ['header1' => 'Owner', 'header2' => 'Next Line'])])[0];

        $this->assertSame('Owner', $representation['billing_name']);
        $this->assertSame('Owner', $uppercase['billing_name']);
    }

    public function test_billing_name_falls_back_to_header4_then_null(): void
    {
        $fromFour = $this->mapper->objects([$this->template(1, 'A', ['header4' => 'Header Four'])])[0];
        $none = $this->mapper->objects([$this->template(2, 'B')])[0];

        $this->assertSame('Header Four', $fromFour['billing_name']);
        $this->assertNull($none['billing_name']);
    }

    public function test_billing_name_is_capped_at_255(): void
    {
        $object = $this->mapper->objects([$this->template(1, 'A', ['header1' => str_repeat('x', 300)])])[0];

        $this->assertSame(255, mb_strlen((string) $object['billing_name']));
    }

    public function test_header_lines_never_set_postal_code_or_city(): void
    {
        $object = $this->mapper->objects([$this->template(1, 'A', ['header1' => 'Owner', 'header2' => 'Street 1', 'header3' => '81101 Košice'])])[0];

        $this->assertArrayNotHasKey('postal_code', $object);
        $this->assertSame('Bratislava', $object['city']);
    }

    public function test_client_pairs_are_a_union_without_duplicates(): void
    {
        $pairs = $this->mapper->clientPairs(
            [$this->template(1, 'A', firm: 2, client: 1), $this->template(2, 'B', firm: 2, client: 1)],
            [['firm' => 2, 'client' => 1], ['firm' => 3, 'client' => 5], ['firm' => 100, 'client' => 9]],
        );

        $this->assertSame([['firm' => 2, 'client' => 1], ['firm' => 3, 'client' => 5], ['firm' => 100, 'client' => 9]], $pairs);
    }

    public function test_other_firms_are_ignored(): void
    {
        $this->assertSame([], $this->mapper->objects([$this->template(1, 'A', firm: 99)]));
        $this->assertSame([], $this->mapper->clientPairs([], [['firm' => 99, 'client' => 1]]));
    }

    public function test_recurring_rows_target_the_house_for_a_merged_stand_and_the_own_object_for_a_standalone_stand(): void
    {
        $rows = $this->mapper->recurringRows([
            $this->template(1, 'House č.1'),
            $this->template(2, 'House č.1 kont. stojisko'),
            $this->template(3, 'Lonely č.2 kont. stojisko'),
        ], []);

        $this->assertSame(['House č.1', 'House č.1', 'Lonely č.2 kont. stojisko'], array_column($rows, 'object_name'));
        $this->assertSame(['House č.1', 'House č.1 kont. stojisko', 'Lonely č.2 kont. stojisko'], array_column($rows, 'name'));
    }

    public function test_recurring_row_texts_items_and_month(): void
    {
        $row = $this->mapper->recurringRows([$this->template(1, 'A', [
            'above' => "Cleaning {mesiac/rok}\n\n", 'services' => 'Stairs',
            'items' => [['name' => ' Cleaning ', 'quantity' => 1, 'price' => 30.5], ['name' => '', 'quantity' => 1, 'price' => 1]],
            'last_billed_month' => '2026-09',
        ])], [])[0];

        $this->assertSame("Cleaning {mesiac/rok}\nStairs", $row['header_text']);
        $this->assertSame([['description' => 'Cleaning', 'quantity' => 1.0, 'unit_price' => 30.5]], $row['items']);
        $this->assertSame('2026-09-01', $row['last_billed_month']?->toDateString());
        $this->assertSame(RecurringFrequencyEnum::Monthly, $row['frequency']);
    }

    public function test_services_already_in_above_are_not_repeated_and_blank_texts_become_null(): void
    {
        $contained = $this->mapper->recurringRows([$this->template(1, 'A', ['above' => 'Clean STAIRS monthly', 'services' => 'stairs'])], [])[0];
        $blank = $this->mapper->recurringRows([$this->template(2, 'B', ['above' => " \n", 'services' => ''])], [])[0];

        $this->assertSame('Clean STAIRS monthly', $contained['header_text']);
        $this->assertNull($blank['header_text']);
    }

    public function test_invalid_or_missing_last_billed_month_is_null(): void
    {
        foreach ([null, '2026-13', '2026-9', 'abc'] as $value) {
            $this->assertNull($this->mapper->recurringRows([$this->template(1, 'A', ['last_billed_month' => $value])], [])[0]['last_billed_month']);
        }
    }

    public function test_manual_rows_keep_their_frequency_object_type_and_never_merge_stands(): void
    {
        $manual = [
            ['id' => 5, 'firm' => 2, 'client' => 1, 'name' => 'Group kont.stojisko', 'object_name' => 'Group Object', 'object_type' => 'office', 'frequency' => 'every_2_months', 'last_billed_month' => '2026-08', 'items' => []],
        ];

        $objects = $this->mapper->manualObjects($manual);
        $rows = $this->mapper->recurringRows([], $manual);

        $this->assertSame('Group Object', $objects[0]['name']);
        $this->assertSame(ObjectTypeEnum::Office, $objects[0]['type']);
        $this->assertSame('Group Object', $rows[0]['object_name']);
        $this->assertSame('Group kont.stojisko', $rows[0]['name']);
        $this->assertSame(RecurringFrequencyEnum::EveryTwoMonths, $rows[0]['frequency']);
        $this->assertSame([['firm' => 2, 'client' => 1]], $this->mapper->clientPairs([], [], $manual));
    }

    public function test_manual_row_with_an_unknown_object_type_throws(): void
    {
        $this->expectException(RuntimeException::class);

        $this->mapper->manualObjects([['id' => 5, 'firm' => 2, 'client' => 1, 'name' => 'X', 'object_type' => 'castle']]);
    }

    public function test_legacy_number_is_parsed_and_a_bad_shape_throws(): void
    {
        $this->assertSame(['year' => 2026, 'last' => 41], $this->mapper->legacyNumber('20260041'));

        $this->expectException(RuntimeException::class);
        $this->mapper->legacyNumber('2026-41');
    }

    public function test_manual_row_without_or_with_an_unknown_frequency_throws_but_a_template_defaults_to_monthly(): void
    {
        $manual = ['id' => 5, 'firm' => 2, 'client' => 1, 'name' => 'X', 'items' => []];

        $this->assertSame(RecurringFrequencyEnum::Monthly, $this->mapper->recurringRows([$this->template(1, 'A')], [])[0]['frequency']);

        foreach ([$manual, [...$manual, 'frequency' => 'weekly']] as $row) {
            try {
                $this->mapper->recurringRows([], [$row]);
                $this->fail('Expected an invalid frequency to throw.');
            } catch (RuntimeException $e) {
                $this->assertStringContainsString('recurring #5', $e->getMessage());
            }
        }

        $this->expectException(RuntimeException::class);
        $this->mapper->recurringRows([$this->template(2, 'B', ['frequency' => 'weekly'])], []);
    }
}
