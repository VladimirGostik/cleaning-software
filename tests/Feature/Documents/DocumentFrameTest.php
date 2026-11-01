<?php

declare(strict_types=1);

namespace Tests\Feature\Documents;

use App\Models\Tenant;
use App\Services\Documents\DocumentFrame;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class DocumentFrameTest extends TestCase
{
    use RefreshDatabase;

    public function test_footer_formats_the_tenant_phone(): void
    {
        $tenant = Tenant::factory()->create(['contact_phone' => '+421900000000', 'contact_email' => 'info@example.sk']);

        $footer = DocumentFrame::footer($tenant);

        $this->assertContains('+421 900 000 000', $footer);
        $this->assertNotContains('+421900000000', $footer);
    }

    public function test_footer_skips_a_missing_phone(): void
    {
        $tenant = Tenant::factory()->create(['contact_phone' => null, 'contact_email' => 'info@example.sk']);

        $footer = DocumentFrame::footer($tenant);

        $this->assertContains('info@example.sk', $footer);
        $this->assertCount(count(array_unique($footer)), $footer);
    }
}
