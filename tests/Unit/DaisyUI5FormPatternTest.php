<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Regression test: DaisyUI 4 -> DaisyUI 5 form pattern migration.
 *
 * DaisyUI 4 used: form-control, label-text, input-bordered, select-bordered, textarea-bordered.
 * DaisyUI 5 replaced these with: fieldset, fieldset-legend, fieldset-label (no *-bordered variants).
 *
 * This test fails when DaisyUI 4 class strings are present and passes when
 * the DaisyUI 5 fieldset/fieldset-legend pattern is correctly used.
 */
final class DaisyUI5FormPatternTest extends TestCase
{
    /** @var array<string, string> */
    private array $sources;

    protected function setUp(): void
    {
        parent::setUp();

        $clientsBase = dirname(__DIR__, 2) . '/resources/js/Components/Clients';
        $objectsBase = dirname(__DIR__, 2) . '/resources/js/Components/Objects';

        $this->sources = [
            'ClientFormDrawer.vue' => (string) file_get_contents($clientsBase . '/ClientFormDrawer.vue'),
            'ContactsListField.vue' => (string) file_get_contents($clientsBase . '/ContactsListField.vue'),
            'ObjectFormDrawer.vue' => (string) file_get_contents($objectsBase . '/ObjectFormDrawer.vue'),
            'ObjectFiltersBar.vue' => (string) file_get_contents($objectsBase . '/ObjectFiltersBar.vue'),
        ];
    }

    #[DataProvider('formComponentProvider')]
    public function test_daisy4_form_control_class_absent(string $filename, string $source): void
    {
        $this->assertStringNotContainsString(
            'form-control',
            $source,
            "{$filename} must not contain DaisyUI 4 'form-control' class — migrate to DaisyUI 5 <fieldset> pattern.",
        );
    }

    #[DataProvider('formComponentProvider')]
    public function test_daisy4_label_text_class_absent(string $filename, string $source): void
    {
        $this->assertStringNotContainsString(
            'label-text',
            $source,
            "{$filename} must not contain DaisyUI 4 'label-text' class — migrate to <legend class=\"fieldset-legend\">.",
        );
    }

    #[DataProvider('formComponentProvider')]
    public function test_daisy4_input_bordered_class_absent(string $filename, string $source): void
    {
        $this->assertStringNotContainsString(
            'input-bordered',
            $source,
            "{$filename} must not contain DaisyUI 4 'input-bordered' class — DaisyUI 5 inputs have no separate bordered variant.",
        );
    }

    #[DataProvider('formComponentProvider')]
    public function test_daisy4_select_bordered_class_absent(string $filename, string $source): void
    {
        $this->assertStringNotContainsString(
            'select-bordered',
            $source,
            "{$filename} must not contain DaisyUI 4 'select-bordered' class.",
        );
    }

    #[DataProvider('formComponentProvider')]
    public function test_daisy4_textarea_bordered_class_absent(string $filename, string $source): void
    {
        $this->assertStringNotContainsString(
            'textarea-bordered',
            $source,
            "{$filename} must not contain DaisyUI 4 'textarea-bordered' class.",
        );
    }

    public function test_client_form_drawer_delegates_daisy5_pattern_to_forms_library(): void
    {
        // ClientFormDrawer now uses FormProvider + TextInput/SelectInput etc. which delegate
        // <fieldset class="fieldset"> / <legend class="fieldset-legend"> to FormField.vue.
        // Verify the delegation — not the inline pattern — is present.
        $source = $this->sources['ClientFormDrawer.vue'];

        $this->assertStringContainsString(
            'FormProvider',
            $source,
            'ClientFormDrawer.vue must use FormProvider from the Forms library; DaisyUI 5 fieldset pattern is applied inside FormField.vue.',
        );
    }

    public function test_forms_library_field_component_uses_fieldset_pattern(): void
    {
        $source = (string) file_get_contents(
            dirname(__DIR__, 2) . '/resources/js/Components/Forms/FormField.vue',
        );

        $this->assertMatchesRegularExpression(
            '/<fieldset\b/',
            $source,
            'FormField.vue must contain a <fieldset> element (DaisyUI 5 form pattern).',
        );

        $this->assertStringContainsString(
            'fieldset-legend',
            $source,
            "FormField.vue must use 'fieldset-legend' class on <legend> elements (DaisyUI 5).",
        );

        $this->assertMatchesRegularExpression(
            '/class="fieldset/',
            $source,
            "FormField.vue must apply the 'fieldset' utility class to <fieldset> elements.",
        );
    }

    public function test_contacts_list_field_no_deprecated_label_text(): void
    {
        // Arrange
        $source = $this->sources['ContactsListField.vue'];

        // Act & Assert
        // In DaisyUI 4 the label wrapper pattern was: <label class="label"><span class="label-text">
        // Absence of label-text class confirms migration away from that pattern.
        $this->assertStringNotContainsString(
            'label-text',
            $source,
            "ContactsListField.vue must not use DaisyUI 4 'label-text' span pattern.",
        );
    }

    public function test_client_form_drawer_source_file_is_readable(): void
    {
        // Arrange
        $path = dirname(__DIR__, 2) . '/resources/js/Components/Clients/ClientFormDrawer.vue';

        // Act & Assert
        $this->assertFileExists($path, 'ClientFormDrawer.vue source file must exist.');
        $this->assertGreaterThan(0, (int) filesize($path), 'ClientFormDrawer.vue must not be empty.');
    }

    public function test_contacts_list_field_source_file_is_readable(): void
    {
        // Arrange
        $path = dirname(__DIR__, 2) . '/resources/js/Components/Clients/ContactsListField.vue';

        // Act & Assert
        $this->assertFileExists($path, 'ContactsListField.vue source file must exist.');
        $this->assertGreaterThan(0, (int) filesize($path), 'ContactsListField.vue must not be empty.');
    }

    public function test_object_form_drawer_source_file_is_readable(): void
    {
        // Arrange
        $path = dirname(__DIR__, 2) . '/resources/js/Components/Objects/ObjectFormDrawer.vue';

        // Act & Assert
        $this->assertFileExists($path, 'ObjectFormDrawer.vue source file must exist.');
        $this->assertGreaterThan(0, (int) filesize($path), 'ObjectFormDrawer.vue must not be empty.');
    }

    public function test_object_filters_bar_source_file_is_readable(): void
    {
        // Arrange
        $path = dirname(__DIR__, 2) . '/resources/js/Components/Objects/ObjectFiltersBar.vue';

        // Act & Assert
        $this->assertFileExists($path, 'ObjectFiltersBar.vue source file must exist.');
        $this->assertGreaterThan(0, (int) filesize($path), 'ObjectFiltersBar.vue must not be empty.');
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function formComponentProvider(): array
    {
        $clientsBase = dirname(__DIR__, 2) . '/resources/js/Components/Clients';
        $objectsBase = dirname(__DIR__, 2) . '/resources/js/Components/Objects';

        return [
            'ClientFormDrawer.vue' => [
                'ClientFormDrawer.vue',
                (string) file_get_contents($clientsBase . '/ClientFormDrawer.vue'),
            ],
            'ContactsListField.vue' => [
                'ContactsListField.vue',
                (string) file_get_contents($clientsBase . '/ContactsListField.vue'),
            ],
            'ObjectFormDrawer.vue' => [
                'ObjectFormDrawer.vue',
                (string) file_get_contents($objectsBase . '/ObjectFormDrawer.vue'),
            ],
            'ObjectFiltersBar.vue' => [
                'ObjectFiltersBar.vue',
                (string) file_get_contents($objectsBase . '/ObjectFiltersBar.vue'),
            ],
        ];
    }
}
