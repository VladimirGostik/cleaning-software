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

        $base = dirname(__DIR__, 2) . '/resources/js/Components/Clients';

        $this->sources = [
            'ClientFormDrawer.vue' => (string) file_get_contents($base . '/ClientFormDrawer.vue'),
            'ContactsListField.vue' => (string) file_get_contents($base . '/ContactsListField.vue'),
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

    public function test_client_form_drawer_uses_fieldset_element(): void
    {
        // Arrange
        $source = $this->sources['ClientFormDrawer.vue'];

        // Act & Assert
        $this->assertMatchesRegularExpression(
            '/<fieldset\b/',
            $source,
            'ClientFormDrawer.vue must contain at least one <fieldset> element (DaisyUI 5 form pattern).',
        );
    }

    public function test_client_form_drawer_uses_fieldset_legend_class(): void
    {
        // Arrange
        $source = $this->sources['ClientFormDrawer.vue'];

        // Act & Assert
        $this->assertStringContainsString(
            'fieldset-legend',
            $source,
            "ClientFormDrawer.vue must use 'fieldset-legend' class on <legend> elements (DaisyUI 5).",
        );
    }

    public function test_client_form_drawer_fieldset_utility_class_present(): void
    {
        // Arrange
        $source = $this->sources['ClientFormDrawer.vue'];

        // Act & Assert
        $this->assertMatchesRegularExpression(
            '/class="fieldset/',
            $source,
            "ClientFormDrawer.vue must apply the 'fieldset' utility class to <fieldset> elements.",
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

    /**
     * @return array<string, array{string, string}>
     */
    public static function formComponentProvider(): array
    {
        $base = dirname(__DIR__, 2) . '/resources/js/Components/Clients';

        return [
            'ClientFormDrawer.vue' => [
                'ClientFormDrawer.vue',
                (string) file_get_contents($base . '/ClientFormDrawer.vue'),
            ],
            'ContactsListField.vue' => [
                'ContactsListField.vue',
                (string) file_get_contents($base . '/ContactsListField.vue'),
            ],
        ];
    }
}
