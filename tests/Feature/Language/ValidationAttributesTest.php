<?php

declare(strict_types=1);

namespace Tests\Feature\Language;

use App\Enums\QuoteKindEnum;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Support\ViewErrorBag;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use Spatie\LaravelData\Data;
use SplFileInfo;
use Tests\TestCase;
use Throwable;

final class ValidationAttributesTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<string> */
    private const LOCALES = ['sk', 'en', 'uk'];

    /** @var list<string> */
    private const NESTED = [
        'items.*.description', 'items.*.quantity', 'items.*.unit', 'items.*.unit_price', 'items.*.discount_percent',
        'items.*.vat_rate', 'items.*.frequency', 'items.*.note',
        'contacts.*.name', 'contacts.*.position', 'contacts.*.email', 'contacts.*.phone',
        'messages.*.role', 'messages.*.content',
        'employment.employment_type', 'employment.position', 'employment.hourly_rate',
        'employment.monthly_salary', 'employment.weekly_hours', 'employment.probation_end_date',
    ];

    public function test_empty_quote_reports_natural_slovak_messages(): void
    {
        $this->actingAsTenantUser('Admin', Tenant::factory()->create());

        $response = $this->post(route('quotes.store'), [
            'kind' => QuoteKindEnum::Itemized->value,
            'client_id' => null,
            'customer_name' => null,
            'issue_date' => now()->toDateString(),
            'valid_until' => now()->addDay()->toDateString(),
            'items' => [],
        ]);

        $response->assertSessionHasErrors(['customer_name', 'items']);
        $errors = $this->sessionErrors();

        $this->assertSame(
            ['Vyberte klienta alebo vyplňte názov odberateľa.'],
            $errors['customer_name'],
        );
        $this->assertSame(['Pridajte aspoň jednu položku.'], $errors['items']);

        foreach (array_merge(...array_values($errors)) as $message) {
            $this->assertDoesNotMatchRegularExpression('/\b(customer name|client id|issue date|valid until)\b/', $message);
        }
    }

    public function test_quote_item_errors_use_the_item_attribute_label(): void
    {
        $this->actingAsTenantUser('Admin', Tenant::factory()->create());

        $response = $this->post(route('quotes.store'), [
            'kind' => QuoteKindEnum::Itemized->value,
            'customer_name' => 'Acme',
            'issue_date' => now()->toDateString(),
            'valid_until' => now()->addDay()->toDateString(),
            'items' => [['id' => null, 'description' => null, 'frequency' => null, 'quantity' => 1, 'unit' => null, 'unit_price' => 10, 'discount_percent' => 0, 'vat_rate' => 23]],
        ]);

        $errors = $this->sessionErrors();

        $this->assertContains('Pole popis položky je povinné.', $errors['items.0.description']);
    }

    public function test_empty_quote_item_line_asks_to_fill_in_the_fields(): void
    {
        $this->actingAsTenantUser('Admin', Tenant::factory()->create());

        $this->post(route('quotes.store'), [
            'kind' => QuoteKindEnum::Itemized->value,
            'customer_name' => 'Acme',
            'issue_date' => now()->toDateString(),
            'valid_until' => now()->addDay()->toDateString(),
            'items' => [['id' => null, 'description' => '', 'frequency' => null, 'quantity' => '', 'unit' => null, 'unit_price' => '', 'discount_percent' => 0, 'vat_rate' => 23]],
        ]);

        $errors = $this->sessionErrors();

        $this->assertContains('Pole popis položky musí byť vyplnené.', $errors['items.0.description']);
        $this->assertContains('Pole množstvo musí byť vyplnené číslom.', $errors['items.0.quantity']);
    }

    public function test_contract_with_empty_subject_asks_to_fill_in_the_field(): void
    {
        $this->actingAsTenantUser('Admin', Tenant::factory()->create());

        $this->post(route('contracts.store'), [
            'title' => 'Zmluva',
            'category' => 'service_agreement',
            'term_type' => 'fixed',
            'contractable_type' => 'cleaning_object',
            'contractable_id' => null,
            'valid_from' => now()->toDateString(),
        ]);

        $errors = $this->sessionErrors();

        $this->assertContains('Pole predmet zmluvy musí byť vyplnené.', $errors['contractable_id']);
    }

    public function test_name_is_labelled_as_a_title_for_things_and_as_a_name_for_people(): void
    {
        $this->actingAsTenantUser('Admin', Tenant::factory()->create());

        $this->post(route('recurring-invoices.store'), ['name' => '']);
        $this->assertContains('Pole názov musí byť vyplnené.', $this->sessionErrors()['name']);

        $this->post(route('roles.store'), ['name' => '', 'permissions' => []]);
        $this->assertContains('Pole názov musí byť vyplnené.', $this->sessionErrors()['name']);

        $this->post(route('users.store'), ['name' => '', 'email' => 'x@example.com']);
        $this->assertContains('Pole meno musí byť vyplnené.', $this->sessionErrors()['name']);
    }

    public function test_every_validated_data_field_has_an_attribute_in_all_locales(): void
    {
        $required = array_unique([...$this->validatedFieldNames(), ...self::NESTED]);

        foreach (self::LOCALES as $locale) {
            $keys = array_keys($this->lang($locale));
            $parents = collect($keys)
                ->filter(fn (string $key): bool => str_starts_with($key, 'attributes.') && substr_count($key, '.') > 1)
                ->map(fn (string $key): string => explode('.', $key)[1])
                ->unique()
                ->all();

            $missing = collect($required)
                ->reject(fn (string $field): bool => in_array(explode('.', $field)[0], $parents, true) && ! str_contains($field, '.'))
                ->reject(fn (string $field): bool => str_contains($field, '.') && ! in_array($field, self::NESTED, true) && in_array(explode('.', $field)[0], $this->flatAttributes($keys), true))
                ->filter(fn (string $field): bool => ! in_array('attributes.'.$field, $keys, true))
                ->values()
                ->all();

            $this->assertSame([], $missing, "Missing validation attributes in {$locale}/validation.json.");
        }
    }

    public function test_attribute_keys_match_across_locales(): void
    {
        $sk = array_keys($this->lang('sk'));

        foreach (['en', 'uk'] as $locale) {
            $keys = array_keys($this->lang($locale));
            sort($keys);
            $expected = $sk;
            sort($expected);

            $this->assertSame($expected, $keys, "{$locale}/validation.json key set differs from sk.");
        }
    }

    /** @return array<string, list<string>> */
    private function sessionErrors(): array
    {
        /** @var ViewErrorBag|array{default: array{messages: array<string, list<string>>}} $errors */
        $errors = session()->get('errors');

        if ($errors instanceof ViewErrorBag) {
            /** @var array<string, list<string>> $messages */
            $messages = $errors->getBag('default')->toArray();

            return $messages;
        }

        return $errors['default']['messages'];
    }

    /** @return array<string, string> */
    private function lang(string $locale): array
    {
        /** @var array<string, string> $lines */
        $lines = json_decode((string) file_get_contents(resource_path("lang/{$locale}/validation.json")), true, flags: JSON_THROW_ON_ERROR);

        return $lines;
    }

    /**
     * @param  list<string>  $keys
     * @return list<string>
     */
    private function flatAttributes(array $keys): array
    {
        $names = [];

        foreach ($keys as $key) {
            if (str_starts_with($key, 'attributes.') && substr_count($key, '.') === 1) {
                $names[] = substr($key, 11);
            }
        }

        return $names;
    }

    /** @return list<string> */
    private function validatedFieldNames(): array
    {
        app()->instance('current_tenant_id', (string) Str::uuid());
        $names = [];
        $root = app_path('Data');

        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root)) as $file) {
            if (! $file instanceof SplFileInfo || $file->getExtension() !== 'php') {
                continue;
            }

            $class = 'App\\Data\\'.str_replace(['/', '.php'], ['\\', ''], substr($file->getPathname(), strlen($root) + 1));

            if (! class_exists($class) || ! is_subclass_of($class, Data::class)) {
                continue;
            }

            $reflection = new ReflectionClass($class);
            $constructor = $reflection->getConstructor();
            $ownsRules = $reflection->hasMethod('rules') && $reflection->getMethod('rules')->getDeclaringClass()->getName() === $class;
            $hasAttribute = false;

            foreach ($constructor?->getParameters() ?? [] as $parameter) {
                foreach ($parameter->getAttributes() as $attribute) {
                    $hasAttribute = $hasAttribute || str_contains($attribute->getName(), 'Validation');
                }
            }

            if (! $ownsRules && ! $hasAttribute) {
                continue;
            }

            foreach ($constructor?->getParameters() ?? [] as $parameter) {
                $names[] = $parameter->getName();
            }

            try {
                array_push($names, ...array_keys($class::getValidationRules([])));
            } catch (Throwable) {
                continue;
            }
        }

        /** @var list<string> $unique */
        $unique = array_values(array_unique($names));

        return $unique;
    }
}
