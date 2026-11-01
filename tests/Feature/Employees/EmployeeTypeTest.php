<?php

declare(strict_types=1);

namespace Tests\Feature\Employees;

use App\Data\Contracts\ContractGenerateData;
use App\Enums\EmployeeTypeEnum;
use App\Enums\EmploymentContractTypeEnum;
use App\Enums\PermissionEnum;
use App\Models\Contract;
use App\Models\EmploymentContract;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use App\Services\Documents\DocumentContextBuilder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Schema;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia;
use Tests\Support\CreatesTaskFixtures;
use Tests\Support\CreatesUsers;
use Tests\TestCase;

final class EmployeeTypeTest extends TestCase
{
    use CreatesTaskFixtures;
    use CreatesUsers;
    use RefreshDatabase;

    private Tenant $firm;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedPermissionCatalog();
        $this->firm = $this->firm('Alpha');
        $this->admin = $this->member($this->firm, 'Admin');
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function storePayload(array $overrides = []): array
    {
        return array_merge([
            'has_app_access' => false,
            'employee_type' => 'brigade',
            'company_ico' => null,
            'company_name' => null,
            'email' => null,
            'first_name' => 'Jana',
            'last_name' => 'Nováková',
            'phone' => null,
            'position' => null,
            'role_name' => null,
            'permissions' => [],
            'employment' => null,
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function updatePayload(array $overrides = []): array
    {
        return array_merge([
            'employee_type' => null,
            'contact_email' => null,
            'company_ico' => null,
            'company_name' => null,
            'first_name' => 'Jana',
            'last_name' => 'Nováková',
            'phone' => null,
            'position' => null,
            'role_name' => null,
            'permissions' => [],
        ], $overrides);
    }

    /**
     * @param  TestResponse<Response>  $response
     * @return list<array<string, mixed>>
     */
    private function rows(TestResponse $response, string $path): array
    {
        $rows = $response->inertiaProps($path);

        /** @var list<array<string, mixed>> */
        return is_array($rows) ? array_values($rows) : [];
    }

    private function asAdmin(): static
    {
        return $this->webAs($this->admin, $this->firm);
    }

    /** @param array<string, mixed> $attributes */
    private function employee(array $attributes = []): TenantMembership
    {
        return TenantMembership::factory()->withProfile()->withoutUser()->create(['tenant_id' => $this->firm->id, ...$attributes]);
    }

    private function contractFor(TenantMembership $employee, EmploymentContractTypeEnum $type, ?string $createdAt = null): Contract
    {
        $contract = Contract::factory()->forMembership($employee)->create(['tenant_id' => $this->firm->id, ...($createdAt === null ? [] : ['created_at' => $createdAt])]);
        EmploymentContract::factory()->forContract($contract)->create(['tenant_id' => $this->firm->id, 'employment_type' => $type]);

        return $contract;
    }

    public function test_type_is_required_on_create_and_must_be_valid(): void
    {
        $this->asAdmin()->post(route('employees.store'), $this->storePayload(['employee_type' => null]))->assertSessionHasErrors('employee_type');
        $this->asAdmin()->post(route('employees.store'), $this->storePayload(['employee_type' => 'student']))->assertSessionHasErrors('employee_type');
    }

    public function test_type_is_stored_without_any_contract(): void
    {
        $this->asAdmin()->post(route('employees.store'), $this->storePayload(['employee_type' => 'tpp']))->assertSessionHasNoErrors();

        $membership = TenantMembership::query()->where('first_name', 'Jana')->firstOrFail();
        $this->assertSame(EmployeeTypeEnum::Tpp, $membership->employee_type);
        $this->assertSame(0, Contract::query()->count());
    }

    public function test_contract_kind_may_differ_from_the_type(): void
    {
        $this->asAdmin()->post(route('employees.store'), $this->storePayload([
            'employee_type' => 'tpp',
            'employment' => ['employment_type' => 'dpp', 'position' => null, 'hourly_rate' => 5, 'monthly_salary' => null, 'weekly_hours' => null, 'probation_end_date' => null],
        ]))->assertSessionHasNoErrors();

        $membership = TenantMembership::query()->where('first_name', 'Jana')->firstOrFail();
        $this->assertSame(EmployeeTypeEnum::Tpp, $membership->employee_type);
        $this->assertSame(1, Contract::query()->count());
    }

    public function test_update_keeps_the_stored_type_when_none_is_sent_and_rejects_invalid_values(): void
    {
        $employee = $this->employee(['employee_type' => EmployeeTypeEnum::Tpp]);

        $this->asAdmin()->put(route('employees.update', $employee), $this->updatePayload())->assertSessionHasNoErrors();
        $this->assertSame(EmployeeTypeEnum::Tpp, $employee->refresh()->employee_type);

        $this->asAdmin()->put(route('employees.update', $employee), $this->updatePayload(['employee_type' => 'nope']))->assertSessionHasErrors('employee_type');
        $this->asAdmin()->put(route('employees.update', $employee), $this->updatePayload(['employee_type' => 'brigade']))->assertSessionHasNoErrors();
        $this->assertSame(EmployeeTypeEnum::Brigade, $employee->refresh()->employee_type);
    }

    public function test_update_without_a_login_requires_names_and_with_a_login_requires_a_role(): void
    {
        $noLogin = $this->employee();
        $withLogin = $this->member($this->firm, 'Interná upratovačka');

        $this->asAdmin()->put(route('employees.update', $noLogin), $this->updatePayload(['first_name' => null, 'last_name' => null]))
            ->assertSessionHasErrors(['first_name', 'last_name']);
        $this->asAdmin()->put(route('employees.update', $this->membershipOf($withLogin, $this->firm)), $this->updatePayload(['role_name' => null]))
            ->assertSessionHasErrors('role_name');
        $this->asAdmin()->put(route('employees.update', $this->membershipOf($withLogin, $this->firm)), $this->updatePayload(['first_name' => null, 'last_name' => null, 'role_name' => 'Sekretárka']))
            ->assertSessionHasNoErrors();
    }

    public function test_update_without_a_login_stores_the_contact_email_and_ignores_role_and_permissions(): void
    {
        $employee = $this->employee();

        $this->asAdmin()->put(route('employees.update', $employee), $this->updatePayload([
            'contact_email' => 'novy@example.com',
            'permissions' => [PermissionEnum::ViewInvoices->value],
        ]))->assertSessionHasNoErrors();

        $this->assertSame('novy@example.com', $employee->refresh()->contact_email);
        $this->assertNull($employee->user_id);
    }

    public function test_self_employed_requires_ico_on_create_and_update(): void
    {
        $this->asAdmin()->post(route('employees.store'), $this->storePayload(['employee_type' => 'self_employed']))->assertSessionHasErrors('company_ico');

        $employee = $this->employee();
        $this->asAdmin()->put(route('employees.update', $employee), $this->updatePayload(['employee_type' => 'self_employed']))->assertSessionHasErrors('company_ico');
    }

    public function test_self_employed_with_ico_stores_it_with_the_optional_firm_name(): void
    {
        $this->asAdmin()->post(route('employees.store'), $this->storePayload([
            'employee_type' => 'self_employed',
            'company_ico' => '12345678',
            'company_name' => 'Jana Nováková s.r.o.',
        ]))->assertSessionHasNoErrors();

        $membership = TenantMembership::query()->where('first_name', 'Jana')->firstOrFail();
        $this->assertSame('12345678', $membership->company_ico);
        $this->assertSame('Jana Nováková s.r.o.', $membership->company_name);

        $this->asAdmin()->put(route('employees.update', $membership), $this->updatePayload(['employee_type' => 'self_employed', 'company_ico' => '87654321', 'company_name' => null]))
            ->assertSessionHasNoErrors();
        $this->assertSame('87654321', $membership->refresh()->company_ico);
        $this->assertNull($membership->company_name);
    }

    public function test_a_custom_role_without_personal_data_access_can_save_self_employed_with_ico_and_viewers_see_it(): void
    {
        $writer = $this->userWithPermission(PermissionEnum::ViewEmployees->value, PermissionEnum::CreateEmployees->value, PermissionEnum::EditEmployees->value);
        $this->actingAs($writer);

        $this->post(route('employees.store'), $this->storePayload([
            'employee_type' => 'self_employed',
            'company_ico' => '12345678',
            'company_name' => 'Firma s.r.o.',
            'national_id' => '900101/1234',
            'street' => 'Tajná 1',
        ]))->assertSessionHasNoErrors();

        $membership = TenantMembership::query()->where('first_name', 'Jana')->firstOrFail();
        $this->assertSame('12345678', $membership->company_ico);
        $this->assertNull($membership->national_id);
        $this->assertNull($membership->street);

        $this->get(route('employees.show', $membership))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('employee.company_ico', '12345678')
            ->where('employee.company_name', 'Firma s.r.o.')
            ->where('personal', null));
    }

    public function test_the_personal_payloads_no_longer_carry_the_company_ico(): void
    {
        $employee = $this->employee(['employee_type' => EmployeeTypeEnum::SelfEmployed, 'company_ico' => '12345678', 'street' => 'Ulica 1']);

        $this->asAdmin()->get(route('employees.show', $employee))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('employee.company_ico', '12345678')
            ->where('personal.street', 'Ulica 1')
            ->missing('personal.company_ico'));
        $this->asAdmin()->get(route('employees.edit', $employee))->assertInertia(fn (AssertableInertia $page) => $page->missing('personal.company_ico'));
    }

    public function test_changing_to_a_non_self_employed_type_clears_the_company_fields_and_contracts_do_not_print_them(): void
    {
        $employee = $this->employee(['employee_type' => EmployeeTypeEnum::SelfEmployed, 'company_ico' => '12345678', 'company_name' => 'Firma s.r.o.']);

        $this->asAdmin()->put(route('employees.update', $employee), $this->updatePayload(['employee_type' => 'tpp']))->assertSessionHasNoErrors();

        $employee->refresh();
        $this->assertNull($employee->company_ico);
        $this->assertNull($employee->company_name);

        $this->bindTenant($this->firm);
        $context = app(DocumentContextBuilder::class)->forContract($employee, $this->firm, $this->terms($employee));
        $this->assertNull($context->values['employee.ico']);
        $this->assertNull($context->values['employee.company_name']);
        $this->assertStringNotContainsString('12345678', $context->blocks['block.parties']);
        $this->assertStringNotContainsString('Firma s.r.o.', $context->blocks['block.parties']);
    }

    public function test_company_name_token_and_party_block_render_when_set(): void
    {
        $employee = $this->employee(['employee_type' => EmployeeTypeEnum::SelfEmployed, 'company_ico' => '12345678', 'company_name' => 'Firma s.r.o.']);
        $this->bindTenant($this->firm);

        $context = app(DocumentContextBuilder::class)->forContract($employee, $this->firm, $this->terms($employee));

        $this->assertSame('Firma s.r.o.', $context->values['employee.company_name']);
        $this->assertStringContainsString('Firma s.r.o.', $context->blocks['block.parties']);
        $this->assertStringContainsString('IČO: 12345678', $context->blocks['block.parties']);
    }

    public function test_a_no_login_employee_uses_the_contact_email_in_documents(): void
    {
        $employee = $this->employee(['contact_email' => 'kontakt@example.com']);
        $this->bindTenant($this->firm);

        $context = app(DocumentContextBuilder::class)->forContract($employee, $this->firm, $this->terms($employee));

        $this->assertSame('kontakt@example.com', $context->values['employee.email']);
    }

    public function test_list_filters_by_type_and_access_within_the_tenant(): void
    {
        $brigade = $this->employee(['employee_type' => EmployeeTypeEnum::Brigade]);
        $tpp = $this->member($this->firm, 'Interná upratovačka');
        $this->membershipOf($tpp, $this->firm)->update(['employee_type' => EmployeeTypeEnum::Tpp]);
        $foreign = TenantMembership::factory()->withoutUser()->create(['tenant_id' => $this->firm('Beta')->id, 'employee_type' => EmployeeTypeEnum::Brigade]);

        $byType = $this->rows($this->asAdmin()->get(route('employees.index', ['filter' => ['employee_type' => 'brigade']])), 'employees.data');
        $this->assertSame([$brigade->id], array_column($byType, 'id'));
        $this->assertNotContains($foreign->id, array_column($byType, 'id'));

        $noAccess = $this->rows($this->asAdmin()->get(route('employees.index', ['filter' => ['has_access' => '0']])), 'employees.data');
        $this->assertSame([$brigade->id], array_column($noAccess, 'id'));

        $withAccess = $this->rows($this->asAdmin()->get(route('employees.index', ['filter' => ['has_access' => '1']])), 'employees.data');
        $this->assertNotContains($brigade->id, array_column($withAccess, 'id'));
        $this->assertContains($this->membershipOf($tpp, $this->firm)->id, array_column($withAccess, 'id'));
    }

    public function test_list_item_carries_the_access_flags_and_falls_back_to_the_contact_email(): void
    {
        $noLogin = $this->employee(['contact_email' => 'kontakt@example.com']);
        $pending = $this->member($this->firm, 'Interná upratovačka', User::factory()->create(['password' => null]));

        $rows = array_column($this->rows($this->asAdmin()->get(route('employees.index')), 'employees.data'), null, 'id');

        $noLoginRow = $rows[$noLogin->id];
        $this->assertNull($noLoginRow['user_id']);
        $this->assertSame('kontakt@example.com', $noLoginRow['email']);
        $this->assertFalse($noLoginRow['has_access']);
        $pendingRow = $rows[$this->membershipOf($pending, $this->firm)->id];
        $this->assertTrue($pendingRow['has_access']);
        $this->assertTrue($pendingRow['invitation_pending']);
    }

    public function test_search_finds_the_contact_email(): void
    {
        $noLogin = $this->employee(['contact_email' => 'unikatny@example.com']);

        $rows = $this->rows($this->asAdmin()->get(route('employees.index', ['filter' => ['search' => 'unikatny']])), 'employees.data');

        $this->assertSame([$noLogin->id], array_column($rows, 'id'));
    }

    public function test_the_backfill_derives_the_type_from_the_latest_live_employment_contract(): void
    {
        $tpp = $this->employee();
        $dpp = $this->employee();
        $dpc = $this->employee();
        $self = $this->employee();
        $latestWins = $this->employee();
        $deleted = $this->employee();
        $none = $this->employee();

        $this->contractFor($tpp, EmploymentContractTypeEnum::Tpp);
        $this->contractFor($dpp, EmploymentContractTypeEnum::Dpp);
        $this->contractFor($dpc, EmploymentContractTypeEnum::Dpc);
        $this->contractFor($self, EmploymentContractTypeEnum::SelfEmployed);
        $this->contractFor($latestWins, EmploymentContractTypeEnum::Dpp, now()->subDays(10)->toDateTimeString());
        $this->contractFor($latestWins, EmploymentContractTypeEnum::Tpp, now()->subDay()->toDateTimeString());
        $this->contractFor($deleted, EmploymentContractTypeEnum::Tpp, now()->subDay()->toDateTimeString());
        $this->contractFor($deleted, EmploymentContractTypeEnum::Dpp, now()->subDays(5)->toDateTimeString());
        Contract::query()->where('contractable_id', $deleted->id)->latest('created_at')->firstOrFail()->delete();

        Schema::table('tenant_memberships', function (Blueprint $table): void {
            $table->dropColumn(['employee_type', 'contact_email', 'company_name']);
        });

        /** @var Migration $migration */
        $migration = require base_path('database/migrations/2026_11_06_000100_add_employee_access_and_type_to_tenant_memberships.php');
        $migration->up(); // @phpstan-ignore method.notFound (anonymous migration class)

        $type = static fn (TenantMembership $m): ?string => TenantMembership::query()->findOrFail($m->id)->employee_type?->value;

        $this->assertSame('tpp', $type($tpp));
        $this->assertSame('brigade', $type($dpp));
        $this->assertSame('brigade', $type($dpc));
        $this->assertSame('self_employed', $type($self));
        $this->assertSame('tpp', $type($latestWins));
        $this->assertSame('brigade', $type($deleted));
        $this->assertNull($type($none));
    }

    private function terms(TenantMembership $employee): ContractGenerateData
    {
        return ContractGenerateData::from([
            'category' => 'employment',
            'term_type' => 'fixed',
            'contractable_type' => 'tenant_membership',
            'contractable_id' => $employee->id,
            'valid_from' => now()->toDateString(),
            'end_date' => now()->addYear()->toDateString(),
            'title' => 'Titul zmluvy',
            'show_header' => true,
            'show_footer' => true,
        ]);
    }

    public function test_update_validation_messages_are_readable(): void
    {
        app()->setLocale('sk');
        $employee = $this->employee();

        $this->asAdmin()->put(route('employees.update', $employee), $this->updatePayload(['first_name' => null, 'last_name' => null, 'contact_email' => 'nope']))
            ->assertSessionHasErrors([
                'first_name' => 'Meno je povinné.',
                'last_name' => 'Priezvisko je povinné.',
                'contact_email' => __('app.employee_validation_email_invalid'),
            ]);
    }

    public function test_access_validation_messages_are_readable(): void
    {
        app()->setLocale('sk');
        $employee = $this->employee();

        $this->asAdmin()->post(route('employees.access.store', $employee), ['email' => '', 'role_name' => ''])
            ->assertSessionHasErrors([
                'email' => __('app.employee_validation_email_required'),
                'role_name' => __('app.employee_validation_role_required'),
            ]);
    }
}
