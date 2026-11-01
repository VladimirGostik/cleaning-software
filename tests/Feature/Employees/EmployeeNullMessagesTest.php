<?php

declare(strict_types=1);

namespace Tests\Feature\Employees;

use App\Models\TenantMembership;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;
use Illuminate\Support\ViewErrorBag;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\CreatesTaskFixtures;
use Tests\TestCase;

final class EmployeeNullMessagesTest extends TestCase
{
    use CreatesTaskFixtures;
    use RefreshDatabase;

    /**
     * @param  TestResponse<Response>  $response
     * @return array<string, list<string>>
     */
    private function errorsOf(TestResponse $response): array
    {
        $response->assertSessionHasErrors();
        $bag = app('session.store')->get('errors');

        /** @var array<string, list<string>> */
        return $bag instanceof ViewErrorBag ? $bag->getBag('default')->toArray() : [];
    }

    /** @return array<string, array{0: mixed}> */
    public static function emptyValues(): array
    {
        return ['null' => [null], 'empty string' => ['']];
    }

    #[DataProvider('emptyValues')]
    public function test_store_gives_the_required_message_for_null_and_empty_values(mixed $empty): void
    {
        app()->setLocale('sk');
        $this->seedPermissionCatalog();
        $firm = $this->firm('Alpha');
        $admin = $this->member($firm, 'Admin');
        $web = $this->webAs($admin, $firm);

        $errors = $this->errorsOf($web->post(route('employees.store'), [
            'has_app_access' => false, 'employee_type' => $empty, 'company_ico' => $empty, 'company_name' => null,
            'email' => $empty, 'first_name' => $empty, 'last_name' => $empty, 'phone' => null, 'position' => null,
            'role_name' => $empty, 'permissions' => [], 'employment' => null,
        ]));
        $this->assertSame(['Typ zamestnanca je potrebné vybrať.'], $errors['employee_type']);
        $this->assertSame(['Meno je povinné.'], $errors['first_name']);
        $this->assertSame(['Priezvisko je povinné.'], $errors['last_name']);

        $errors = $this->errorsOf($web->post(route('employees.store'), [
            'has_app_access' => true, 'employee_type' => 'self_employed', 'company_ico' => $empty, 'company_name' => null,
            'email' => $empty, 'first_name' => null, 'last_name' => null, 'phone' => null, 'position' => null,
            'role_name' => $empty, 'permissions' => [], 'employment' => null,
        ]));
        $this->assertSame(['E-mail je povinný.'], $errors['email']);
        $this->assertSame(['Rola je povinná.'], $errors['role_name']);
        $this->assertSame(['IČO je povinné.'], $errors['company_ico']);
    }

    #[DataProvider('emptyValues')]
    public function test_update_gives_the_required_message_for_null_and_empty_values(mixed $empty): void
    {
        app()->setLocale('sk');
        $this->seedPermissionCatalog();
        $firm = $this->firm('Alpha');
        $admin = $this->member($firm, 'Admin');
        $noLogin = TenantMembership::factory()->withoutUser()->create(['tenant_id' => $firm->id]);
        $withLogin = $this->membershipOf($this->member($firm, 'Sekretárka'), $firm);
        $web = $this->webAs($admin, $firm);
        $base = ['employee_type' => 'self_employed', 'contact_email' => null, 'company_ico' => $empty, 'company_name' => null,
            'first_name' => $empty, 'last_name' => $empty, 'phone' => null, 'position' => null, 'role_name' => $empty, 'permissions' => []];

        $errors = $this->errorsOf($web->put(route('employees.update', $noLogin), $base));
        $this->assertSame(['Meno je povinné.'], $errors['first_name']);
        $this->assertSame(['Priezvisko je povinné.'], $errors['last_name']);
        $this->assertSame(['IČO je povinné.'], $errors['company_ico']);

        $errors = $this->errorsOf($web->put(route('employees.update', $withLogin), $base));
        $this->assertSame(['Rola je povinná.'], $errors['role_name']);
    }

    #[DataProvider('emptyValues')]
    public function test_access_gives_the_required_message_for_null_and_empty_values(mixed $empty): void
    {
        app()->setLocale('sk');
        $this->seedPermissionCatalog();
        $firm = $this->firm('Alpha');
        $admin = $this->member($firm, 'Admin');
        $noLogin = TenantMembership::factory()->withoutUser()->create(['tenant_id' => $firm->id]);

        $errors = $this->errorsOf($this->webAs($admin, $firm)->post(route('employees.access.store', $noLogin), ['email' => $empty, 'role_name' => $empty, 'permissions' => []]));
        $this->assertSame(['E-mail je povinný.'], $errors['email']);
        $this->assertSame(['Rola je povinná.'], $errors['role_name']);
    }
}
