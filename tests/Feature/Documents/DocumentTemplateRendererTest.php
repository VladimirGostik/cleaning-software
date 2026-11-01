<?php

declare(strict_types=1);

namespace Tests\Feature\Documents;

use App\Enums\DocumentTypeEnum;
use App\Services\Documents\DocumentContextBuilder;
use App\Services\Documents\DocumentHtmlSanitizer;
use App\Services\Documents\DocumentRenderContext;
use App\Services\Documents\DocumentTemplateRenderer;
use Tests\TestCase;

final class DocumentTemplateRendererTest extends TestCase
{
    private DocumentTemplateRenderer $renderer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->renderer = app(DocumentTemplateRenderer::class);
    }

    public function test_values_are_html_escaped(): void
    {
        $html = $this->renderer->render('<p>{{client.name}}</p>', new DocumentRenderContext(['client.name' => '<b>"Acme" & Co</b>']));

        $this->assertSame('<p>&lt;b&gt;&quot;Acme&quot; &amp; Co&lt;/b&gt;</p>', $html);
    }

    public function test_resolution_is_single_pass(): void
    {
        $html = $this->renderer->render('<p>{{client.name}} {{tenant.name}}</p>', new DocumentRenderContext([
            'client.name' => '{{tenant.name}}',
            'tenant.name' => 'Firma',
        ]));

        $this->assertSame('<p>{{tenant.name}} Firma</p>', $html);
    }

    public function test_text_token_without_value_stays_as_token_and_survives_re_resolve(): void
    {
        $ctx = new DocumentRenderContext(['client.name' => null]);

        $first = $this->renderer->render('<p>Klient {{client.name}}</p>', $ctx);
        $second = $this->renderer->render($first, $ctx);

        $this->assertSame('<p>Klient {{client.name}}</p>', $first);
        $this->assertSame($first, $second);
        $this->assertSame(['client.name'], $this->renderer->unresolvedTokens($second));
    }

    public function test_tokens_with_spaces_are_normalized_and_unknown_keys_stay_verbatim(): void
    {
        $html = $this->renderer->render('<p>{{ client.name }} {{made.up}}</p>', new DocumentRenderContext(['client.name' => null]));

        $this->assertSame('<p>{{client.name}} {{made.up}}</p>', $html);
    }

    public function test_optional_token_without_value_is_removed_and_empty_paragraph_dropped(): void
    {
        $ctx = new DocumentRenderContext(['client.dic' => null, 'client.ico' => '123']);

        $html = $this->renderer->render('<p>A</p><p>{{client.dic}}</p><p>IČO {{client.ico}} DIČ {{client.dic}}</p><p></p>', $ctx);

        $this->assertSame('<p>A</p><p>IČO 123 DIČ </p><p></p>', $html);
    }

    public function test_flag_paragraph_is_kept_or_dropped(): void
    {
        $ctx = new DocumentRenderContext(flags: ['if.fixed_term' => true, 'if.indefinite_term' => false]);

        $html = $this->renderer->render('<p>{{if.fixed_term}}Určitá</p><p>{{if.indefinite_term}}Neurčitá</p>', $ctx);

        $this->assertSame('<p>Určitá</p>', $html);
    }

    public function test_block_paragraph_is_replaced_by_block_html_without_wrapper(): void
    {
        $ctx = new DocumentRenderContext(blocks: ['block.parties' => '<table><tbody><tr><td>x</td></tr></tbody></table>']);

        $html = $this->renderer->render('<p>Úvod</p><p>{{block.parties}}</p>', $ctx);

        $this->assertSame('<p>Úvod</p><table><tbody><tr><td>x</td></tr></tbody></table>', $html);
    }

    public function test_violations_list_tokens_unavailable_for_the_type(): void
    {
        $this->assertSame(
            ['employee.name'],
            $this->renderer->violations(DocumentTypeEnum::ServiceAgreement, '<p>{{client.name}} {{employee.name}}</p>'),
        );
    }

    public function test_highlight_missing_wraps_remaining_tokens_in_mark(): void
    {
        $html = $this->renderer->highlightMissing('<p>{{client.name}}</p>', DocumentTypeEnum::ServiceAgreement);

        $this->assertSame('<p><mark>('.__('app.document_token_client_name').')</mark></p>', $html);
    }

    public function test_every_block_survives_the_sanitizer_unchanged(): void
    {
        $sanitizer = app(DocumentHtmlSanitizer::class);
        $context = app(DocumentContextBuilder::class)->sample(DocumentTypeEnum::ServiceAgreement, null);

        $this->assertNotEmpty($context->blocks);

        foreach ($context->blocks as $key => $block) {
            $this->assertSame($block, $sanitizer->sanitize($block), "block {$key} changed by the sanitizer");
        }
    }

    public function test_sanitizer_strips_scripts_and_unsafe_styles(): void
    {
        $html = app(DocumentHtmlSanitizer::class)->sanitize(
            '<p style="text-align: center; background: url(x); color: red" onclick="x()">a<script>alert(1)</script></p>',
        );

        $this->assertSame('<p style="text-align: center; color: red;">a</p>', $html);
    }
}
