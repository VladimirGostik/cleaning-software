<?php

declare(strict_types=1);

namespace Tests\Unit\Ai;

use App\Ai\Agents\AssistantAgent;
use App\Services\Ai\AiClientOptions;
use Laravel\Ai\Enums\Lab;
use Tests\TestCase;

final class AssistantAgentProviderOptionsTest extends TestCase
{
    /** @param  array<string, string>|null  $thinking */
    private function agent(string $context = 'ctx', ?array $thinking = ['type' => 'between_tools']): AssistantAgent
    {
        return new AssistantAgent('rules', $context, [], [], 'medium', $thinking);
    }

    public function test_anthropic_gets_cached_system_blocks_effort_and_thinking(): void
    {
        $options = $this->agent()->providerOptions(Lab::Anthropic);

        $this->assertSame([
            'system' => [
                ['type' => 'text', 'text' => 'rules', 'cache_control' => ['type' => 'ephemeral', 'ttl' => '1h']],
                ['type' => 'text', 'text' => 'ctx'],
            ],
            'cache_control' => ['type' => 'ephemeral'],
            'output_config' => ['effort' => 'medium'],
            'thinking' => ['type' => 'between_tools'],
        ], $options);
        $this->assertSame($options, $this->agent()->providerOptions('anthropic'));
    }

    public function test_thinking_is_omitted_when_null_and_empty_context_drops_its_block(): void
    {
        $options = $this->agent('', null)->providerOptions(Lab::Anthropic);

        $this->assertArrayNotHasKey('thinking', $options);
        $this->assertCount(1, (array) $options['system']);
    }

    public function test_other_providers_get_no_options_and_instructions_stay_a_string(): void
    {
        $this->assertSame([], $this->agent()->providerOptions(Lab::OpenAI));
        $this->assertSame([], $this->agent()->providerOptions('openai'));
        $this->assertSame("rules\nctx", $this->agent()->instructions());
    }

    public function test_configured_efforts_stay_within_what_between_tools_allows(): void
    {
        $options = new AiClientOptions;

        $this->assertContains($options->assistantEffort, ['low', 'medium', 'high']);
        $this->assertContains($options->taskRunEffort, ['low', 'medium', 'high']);
    }

    public function test_thinking_off_is_looked_up_by_exact_model_id(): void
    {
        config(['ai.model' => 'claude-sonnet-5-5']);
        $this->assertSame(['type' => 'between_tools'], (new AiClientOptions)->thinkingOff());

        config(['ai.model' => 'claude-opus-5-5']);
        $this->assertNull((new AiClientOptions)->thinkingOff());

        config(['ai.model' => 'vendor.model-1.5', 'ai.thinking_off' => ['vendor.model-1.5' => ['type' => 'disabled']]]);
        $this->assertSame(['type' => 'disabled'], (new AiClientOptions)->thinkingOff());
    }
}
