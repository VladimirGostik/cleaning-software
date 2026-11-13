<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Data\Contacts\ContactData;
use App\Data\Contacts\ContactPatchData;
use App\Models\Activity;
use App\Models\CleaningObject;
use App\Models\Client;
use App\Models\Contact;
use App\Models\Tenant;
use App\Services\ContactService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ContactServiceTest extends TestCase
{
    use RefreshDatabase;

    private Client $client;

    private ContactService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);
        $this->client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $this->service = app(ContactService::class);
    }

    private function data(string $name, bool $primary = false): ContactData
    {
        return new ContactData(id: 'ignored', name: $name, position: 'Správca', email: $name.'@example.com', phone: '0900', is_primary: $primary);
    }

    public function test_the_first_contact_becomes_primary_even_when_sent_as_not_primary(): void
    {
        $contact = $this->service->create($this->client, $this->data('Anna'));

        $this->assertTrue($contact->refresh()->is_primary);
        $this->assertNotSame('ignored', $contact->id);
    }

    public function test_a_second_contact_is_not_primary_and_the_first_stays_primary(): void
    {
        $first = $this->service->create($this->client, $this->data('Anna'));
        $second = $this->service->create($this->client, $this->data('Boris'));

        $this->assertFalse($second->refresh()->is_primary);
        $this->assertTrue($first->refresh()->is_primary);
    }

    public function test_create_as_primary_demotes_the_previous_primary_in_one_batch(): void
    {
        $first = $this->service->create($this->client, $this->data('Anna'));
        Activity::query()->delete();

        $second = $this->service->create($this->client, $this->data('Boris', true));

        $this->assertTrue($second->refresh()->is_primary);
        $this->assertFalse($first->refresh()->is_primary);
        $batches = Activity::query()->whereNotNull('batch_uuid')->pluck('batch_uuid')->unique();
        $this->assertCount(1, $batches);
        $this->assertSame(2, Activity::query()->where('batch_uuid', $batches->first())->count());
    }

    public function test_update_with_is_primary_demotes_the_previous_primary_in_one_batch(): void
    {
        $first = $this->service->create($this->client, $this->data('Anna'));
        $second = $this->service->create($this->client, $this->data('Boris'));
        Activity::query()->delete();

        $this->service->update($second, new ContactPatchData(is_primary: true));

        $this->assertTrue($second->refresh()->is_primary);
        $this->assertFalse($first->refresh()->is_primary);
        $this->assertCount(1, Activity::query()->whereNotNull('batch_uuid')->pluck('batch_uuid')->unique());
        $this->assertSame(2, Activity::query()->count());
    }

    public function test_deleting_the_primary_promotes_the_remaining_contact(): void
    {
        $first = $this->service->create($this->client, $this->data('Anna'));
        $second = $this->service->create($this->client, $this->data('Boris'));

        $this->service->delete($first);

        $this->assertSoftDeleted($first);
        $this->assertTrue($second->refresh()->is_primary);
    }

    public function test_deleting_a_non_primary_keeps_the_primary_and_deleting_the_only_one_leaves_none(): void
    {
        $first = $this->service->create($this->client, $this->data('Anna'));
        $second = $this->service->create($this->client, $this->data('Boris'));

        $this->service->delete($second);
        $this->assertTrue($first->refresh()->is_primary);

        $this->service->delete($first);
        $this->assertSame(0, Contact::query()->count());
    }

    public function test_promotion_after_deleting_the_primary_picks_the_first_by_name(): void
    {
        $primary = $this->service->create($this->client, $this->data('Zora'));
        $this->service->create($this->client, $this->data('Boris'));
        $anna = $this->service->create($this->client, $this->data('Anna'));

        $this->service->delete($primary);

        $this->assertTrue($anna->refresh()->is_primary);
    }

    public function test_update_with_only_phone_leaves_the_other_fields_and_null_email_clears_it(): void
    {
        $contact = $this->service->create($this->client, $this->data('Anna'));

        $this->service->update($contact, new ContactPatchData(phone: '0911'));
        $contact->refresh();
        $this->assertSame('0911', $contact->phone);
        $this->assertSame('Anna', $contact->name);
        $this->assertSame('Správca', $contact->position);
        $this->assertSame('Anna@example.com', $contact->email);

        $this->service->update($contact, new ContactPatchData(email: null));
        $this->assertNull($contact->refresh()->email);
        $this->assertSame('0911', $contact->phone);
    }

    public function test_an_object_owner_follows_the_same_primary_rule(): void
    {
        $object = CleaningObject::factory()->create(['tenant_id' => $this->client->tenant_id, 'client_id' => $this->client->id]);

        $first = $this->service->create($object, $this->data('Anna'));
        $second = $this->service->create($object, $this->data('Boris', true));

        $this->assertFalse($first->refresh()->is_primary);
        $this->assertTrue($second->refresh()->is_primary);

        $this->service->update($first, new ContactPatchData(is_primary: true));
        $this->assertTrue($first->refresh()->is_primary);
        $this->assertFalse($second->refresh()->is_primary);

        $this->service->delete($first);
        $this->assertTrue($second->refresh()->is_primary);
    }
}
