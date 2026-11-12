<?php

declare(strict_types=1);

namespace Tests\Feature\Search;

use App\Models\CleaningObject;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Quote;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class AccentInsensitiveSearchTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();

        $owner = User::factory()->create(['is_active' => true, 'locale' => 'sk']);
        $this->tenant = Tenant::factory()->forOwner($owner)->create();
        $this->actingAsTenantUser('Admin', $this->tenant, $owner);
        $this->client = Client::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Rajecká s.r.o.']);
    }

    /** @return array<string, array{0: string}> */
    public static function needles(): array
    {
        return [
            'prefix' => ['raje'],
            'unaccented' => ['rajecka'],
            'upper accented' => ['RAJECKÁ'],
        ];
    }

    /** @param array<string, string> $query */
    private function assertFinds(string $route, array $query, string $prop, int $count): void
    {
        $this->get(route($route, $query))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->has($prop, $count));
    }

    #[DataProvider('needles')]
    public function test_objects_search_and_name_filter(string $needle): void
    {
        CleaningObject::factory()->create(['tenant_id' => $this->tenant->id, 'client_id' => $this->client->id, 'name' => 'Rajecká 16']);
        CleaningObject::factory()->create(['tenant_id' => $this->tenant->id, 'client_id' => $this->client->id, 'name' => 'Nitrianska 2']);

        $this->assertFinds('objects.index', ['filter[search]' => $needle], 'objects.data', 1);
        $this->assertFinds('objects.index', ['filter[name]' => '~:'.$needle], 'objects.data', 1);
    }

    public function test_objects_exact_name_filter_ignores_accents(): void
    {
        CleaningObject::factory()->create(['tenant_id' => $this->tenant->id, 'client_id' => $this->client->id, 'name' => 'Rajecká 16']);

        $this->assertFinds('objects.index', ['filter[name]' => 'rajecka 16'], 'objects.data', 1);
        $this->assertFinds('objects.index', ['filter[name]' => '!=:rajecka 16'], 'objects.data', 0);
    }

    #[DataProvider('needles')]
    public function test_clients_search_and_name_filter(string $needle): void
    {
        Client::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Iný klient']);

        $this->assertFinds('clients.index', ['filter[name]' => '~:'.$needle], 'clients.data', 1);
    }

    #[DataProvider('needles')]
    public function test_quotes_search_by_subject(string $needle): void
    {
        Quote::factory()->create(['tenant_id' => $this->tenant->id, 'client_id' => null, 'subject' => 'Rajecká - upratovanie', 'customer_name' => 'X']);
        Quote::factory()->create(['tenant_id' => $this->tenant->id, 'client_id' => null, 'subject' => 'Iné', 'customer_name' => 'Y']);

        $this->assertFinds('quotes.index', ['filter[search]' => $needle], 'quotes.data', 1);
    }

    #[DataProvider('needles')]
    public function test_invoices_customer_name_filter(string $needle): void
    {
        Invoice::factory()->create(['tenant_id' => $this->tenant->id, 'client_id' => $this->client->id, 'customer_name' => 'Rajecká s.r.o.']);
        Invoice::factory()->create(['tenant_id' => $this->tenant->id, 'client_id' => $this->client->id, 'customer_name' => 'Other']);

        $this->assertFinds('invoices.index', ['filter[customer_name]' => '~:'.$needle], 'invoices.data', 1);
    }

    public function test_users_name_filter(): void
    {
        $member = User::factory()->create(['name' => 'Štefan Rajecký', 'is_active' => true]);
        $this->tenant->memberships()->create(['user_id' => $member->id, 'is_active' => true, 'joined_at' => now()]);

        $this->assertFinds('users.index', ['filter[name]' => '~:stefan raj'], 'users.data', 1);
    }

    #[DataProvider('needles')]
    public function test_tasks_search_by_title_and_description(string $needle): void
    {
        $admin = User::query()->firstOrFail();
        Task::factory()->forTenant($this->tenant)->createdBy($admin)->create(['title' => 'Rajecká - kľúče', 'description' => null]);
        Task::factory()->forTenant($this->tenant)->createdBy($admin)->create(['title' => 'Iné', 'description' => 'Pozri Rajecká 16']);
        Task::factory()->forTenant($this->tenant)->createdBy($admin)->create(['title' => 'Nitra', 'description' => 'nič']);

        $this->assertFinds('tasks.index', ['filter[search]' => $needle, 'view' => 'table'], 'tasks.data', 2);
    }
}
