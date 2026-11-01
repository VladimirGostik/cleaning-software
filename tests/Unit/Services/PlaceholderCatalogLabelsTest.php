<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\PlaceholderResolverService;
use Tests\TestCase;

final class PlaceholderCatalogLabelsTest extends TestCase
{
    public function test_every_invoice_mail_token_label_is_translated_in_each_locale(): void
    {
        foreach (['sk', 'en', 'uk'] as $locale) {
            app()->setLocale($locale);

            $tokens = app(PlaceholderResolverService::class)->invoiceMailCatalog()->tokens;

            $this->assertNotEmpty($tokens);

            foreach ($tokens as $token) {
                $this->assertStringStartsNotWith('app.', $token->label, "{$locale}: {$token->token}");
            }
        }
    }
}
