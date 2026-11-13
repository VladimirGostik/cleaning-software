<?php

declare(strict_types=1);

namespace Tests\Feature\Objects;

use App\Models\Activity;
use App\Models\ObjectVisitItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Tests\Support\CreatesWorkRecordFixtures;
use Tests\TestCase;

final class ObjectVisitItemsTest extends TestCase
{
    use CreatesWorkRecordFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedPermissionCatalog();
    }

    /** @return list<array<string, mixed>> */
    private function rows(ObjectVisitItem ...$items): array
    {
        return array_values(array_map(static fn (ObjectVisitItem $item): array => ['id' => $item->id, 'name' => $item->name, 'price' => $item->price], $items));
    }

    public function test_saves_reorders_renames_and_removes_items(): void
    {
        $firm = $this->firm();
        $admin = $this->member($firm, 'Admin');
        $object = $this->objectIn($firm);
        $items = $this->itemsFor($object, ['Kosenie' => '10.00', 'Zametanie' => '5.50', 'Okna' => '30.00']);

        $response = $this->webAs($admin, $firm)->put(route('objects.visit-items.update', $object), ['items' => [
            ['id' => $items['Okna']->id, 'name' => 'Okna', 'price' => '31.50'],
            ['id' => $items['Kosenie']->id, 'name' => 'Kosenie trávy', 'price' => 12],
            ['id' => null, 'name' => 'Schody', 'price' => 0],
        ]]);

        $response->assertRedirect()->assertSessionHas('success', __('app.object_visit_items_saved'));
        $live = $object->visitItems()->get();
        $this->assertSame(['Okna', 'Kosenie trávy', 'Schody'], $live->pluck('name')->all());
        $this->assertSame(['31.50', '12.00', '0.00'], $live->pluck('price')->all());
        $this->assertSame([0, 1, 2], $live->pluck('position')->all());
        $this->assertSoftDeleted('object_visit_items', ['id' => $items['Zametanie']->id]);
        $this->assertSame($items['Kosenie']->id, $live->pluck('id')->all()[1]);
    }

    public function test_a_removed_name_can_be_reused_and_two_names_can_be_swapped(): void
    {
        $firm = $this->firm();
        $admin = $this->member($firm, 'Admin');
        $object = $this->objectIn($firm);
        $items = $this->itemsFor($object, ['A' => '1.00', 'B' => '2.00', 'C' => '3.00']);

        $this->webAs($admin, $firm)->put(route('objects.visit-items.update', $object), ['items' => [
            ['id' => $items['A']->id, 'name' => 'B', 'price' => '1.00'],
            ['id' => $items['B']->id, 'name' => 'A', 'price' => '2.00'],
            ['id' => null, 'name' => 'C', 'price' => '4.00'],
        ]])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame(['B', 'A', 'C'], $object->visitItems()->pluck('name')->all());
        $this->assertSoftDeleted('object_visit_items', ['id' => $items['C']->id]);
        $this->assertSame(3, $object->visitItems()->count());
    }

    public function test_an_empty_list_removes_every_item(): void
    {
        $firm = $this->firm();
        $admin = $this->member($firm, 'Admin');
        $object = $this->objectIn($firm);
        $this->itemsFor($object);

        $this->webAs($admin, $firm)->put(route('objects.visit-items.update', $object), ['items' => []])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame(0, $object->visitItems()->count());
    }

    public function test_validation(): void
    {
        $firm = $this->firm();
        $admin = $this->member($firm, 'Admin');
        $object = $this->objectIn($firm);
        $other = $this->objectIn($firm, 'Other');
        $items = $this->itemsFor($object);
        $foreign = $this->itemsFor($other, ['Cudzie' => '1.00']);
        $put = fn (array $rows) => $this->webAs($admin, $firm)->put(route('objects.visit-items.update', $object), ['items' => $rows]);

        $put([['id' => null, 'name' => 'A', 'price' => 1], ['id' => null, 'name' => ' a ', 'price' => 2]])->assertSessionHasErrors('items');
        $put([['id' => $foreign['Cudzie']->id, 'name' => 'Cudzie', 'price' => 1]])->assertSessionHasErrors('items.0.id');
        $put([['id' => (string) Str::uuid(), 'name' => 'X', 'price' => 1]])->assertSessionHasErrors('items.0.id');
        $put([['id' => null, 'name' => '', 'price' => 1]])->assertSessionHasErrors('items.0.name');
        $put([['id' => null, 'name' => str_repeat('a', 256), 'price' => 1]])->assertSessionHasErrors('items.0.name');
        $put([['id' => null, 'name' => 'X', 'price' => -1]])->assertSessionHasErrors('items.0.price');
        $put([['id' => null, 'name' => 'X', 'price' => '1.234']])->assertSessionHasErrors('items.0.price');
        $put([['id' => null, 'name' => 'X', 'price' => 'abc']])->assertSessionHasErrors('items.0.price');
        $put([['id' => null, 'name' => 'X', 'price' => '']])->assertSessionHasErrors('items.0.price');
        $put([['id' => null, 'name' => 'X', 'price' => '100000000']])->assertSessionHasErrors('items.0.price');
        $put(array_map(static fn (int $i): array => ['id' => null, 'name' => "N$i", 'price' => 1], range(1, 51)))->assertSessionHasErrors('items');
        $this->webAs($admin, $firm)->put(route('objects.visit-items.update', $object), [])->assertSessionHasErrors('items');
        $this->webAs($admin, $firm)->put(route('objects.visit-items.update', $object), ['items' => 'x'])->assertSessionHasErrors('items');

        $this->assertSame(['Kosenie', 'Zametanie'], $object->visitItems()->pluck('name')->all());
        $this->assertSame($items['Kosenie']->id, $object->visitItems()->firstOrFail()->id);
        $this->assertSame(1, $other->visitItems()->count());
    }

    public function test_fifty_items_are_accepted(): void
    {
        $firm = $this->firm();
        $admin = $this->member($firm, 'Admin');
        $object = $this->objectIn($firm);

        $this->webAs($admin, $firm)->put(route('objects.visit-items.update', $object), ['items' => array_map(static fn (int $i): array => ['id' => null, 'name' => "N$i", 'price' => 1], range(1, 50))])
            ->assertSessionHasNoErrors();

        $this->assertSame(50, $object->visitItems()->count());
    }

    public function test_only_edit_objects_with_view_all_objects_may_save_and_a_foreign_object_is_not_found(): void
    {
        $firm = $this->firm();
        $object = $this->objectIn($firm);
        $items = $this->itemsFor($object);
        $payload = ['items' => $this->rows($items['Kosenie'])];

        foreach (['Interná upratovačka', 'Vedúca', 'Účtovníčka'] as $role) {
            $this->webAs($this->member($firm, $role), $firm)->put(route('objects.visit-items.update', $object), $payload)->assertForbidden();
        }

        $this->webAs($this->member($firm, 'Sekretárka'), $firm)->put(route('objects.visit-items.update', $object), $payload)->assertRedirect();
        $this->assertSame(1, $object->visitItems()->count());

        $b = $this->firm('B');
        $this->webAs($this->member($firm, 'Admin'), $firm)->put(route('objects.visit-items.update', $this->objectIn($b)), $payload)->assertNotFound();
    }

    public function test_a_price_change_is_in_the_activity_log(): void
    {
        $firm = $this->firm();
        $admin = $this->member($firm, 'Admin');
        $object = $this->objectIn($firm);
        $items = $this->itemsFor($object, ['Kosenie' => '10.00']);

        $this->webAs($admin, $firm)->put(route('objects.visit-items.update', $object), ['items' => [['id' => $items['Kosenie']->id, 'name' => 'Kosenie', 'price' => '11.00']]])->assertRedirect();

        $entry = Activity::query()->where('subject_id', $items['Kosenie']->id)->where('event', 'updated')->firstOrFail();
        $this->assertSame('11.00', Arr::get($entry->attribute_changes?->toArray() ?? [], 'attributes.price'));
        $this->assertSame('10.00', Arr::get($entry->attribute_changes?->toArray() ?? [], 'old.price'));
    }

    public function test_show_props_for_the_office_and_null_for_a_cleaner(): void
    {
        $firm = $this->firm();
        $admin = $this->member($firm, 'Admin');
        $cleaner = $this->member($firm, 'Interná upratovačka');
        $object = $this->objectIn($firm);
        $this->itemsFor($object);
        $this->assignedJob($cleaner, $firm, $object);

        $this->webAs($admin, $firm)->get(route('objects.show', $object))->assertInertia(fn (AssertableInertia $page) => $page
            ->has('visitItems', 2)
            ->where('visitItems.0.name', 'Kosenie')
            ->where('visitItems.0.price', '10.00')
            ->where('visitRecords.month', now(config()->string('scheduling.timezone'))->format('Y-m')));

        $this->webAs($cleaner, $firm)->get(route('objects.show', $object))->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->where('visitItems', null)
            ->where('visitRecords', null));
    }

    public function test_a_malformed_records_month_falls_back_to_the_current_month(): void
    {
        $firm = $this->firm();
        $admin = $this->member($firm, 'Admin');
        $object = $this->objectIn($firm);
        $current = now(config()->string('scheduling.timezone'))->format('Y-m');

        foreach (['x', '2026-13', '2026-1', ['a']] as $value) {
            $this->webAs($admin, $firm)->get(route('objects.show', ['object' => $object, 'records_month' => $value]))
                ->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->where('visitRecords.month', $current));
        }

        $this->webAs($admin, $firm)->get(route('objects.show', ['object' => $object, 'records_month' => '2026-05']))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('visitRecords.month', '2026-05'));
    }
}
