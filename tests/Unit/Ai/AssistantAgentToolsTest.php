<?php

declare(strict_types=1);

namespace Tests\Unit\Ai;

use App\Ai\Tools\AssistantToolbox;
use App\Mcp\Servers\CleanMasterServer;
use App\Mcp\Tools\ConfirmActionTool;
use App\Mcp\Tools\DeleteGlobalTaskTool;
use App\Mcp\Tools\PrepareActionTool;
use App\Services\Ai\AssistantTurn;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Laravel\Ai\ObjectSchema;
use Laravel\Ai\Tools\ToolNameResolver;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use ReflectionClass;
use Tests\TestCase;

final class AssistantAgentToolsTest extends TestCase
{
    /** @return list<string> */
    private function serverToolNames(): array
    {
        return array_map(fn (string $class): string => app($class)->name(), CleanMasterServer::TOOLS);
    }

    public function test_the_agents_tools_are_the_server_tools_minus_the_explicit_exclusions(): void
    {
        $adapters = (new AssistantToolbox)->make(new AssistantTurn);
        $names = array_map(ToolNameResolver::resolve(...), $adapters);

        $excluded = [app(ConfirmActionTool::class)->name(), app(DeleteGlobalTaskTool::class)->name()];

        $this->assertEqualsCanonicalizing(array_values(array_diff($this->serverToolNames(), $excluded)), $names);
        $this->assertContains('prepare_action', $names);
        $this->assertNotContains('confirm_action', $names);
        $this->assertNotContains('delete_global_task', $names);
        $this->assertCount(count($names), array_unique($names));
    }

    public function test_every_destructive_tool_is_prepare_action_an_update_or_explicitly_excluded(): void
    {
        foreach (CleanMasterServer::TOOLS as $class) {
            $attributes = (new ReflectionClass($class))->getAttributes(IsDestructive::class);
            $destructive = $attributes !== [] && $attributes[0]->newInstance()->value;

            if (! $destructive) {
                continue;
            }

            $allowed = $class === PrepareActionTool::class
                || str_starts_with(class_basename($class), 'Update')
                || in_array($class, AssistantToolbox::EXCLUDED, true);

            $this->assertTrue($allowed, $class.' is destructive: make it prepare_action, an update_* tool or add it to AssistantToolbox::EXCLUDED.');
        }
    }

    public function test_every_adapter_schema_maps_to_a_valid_provider_definition(): void
    {
        foreach ((new AssistantToolbox)->make(new AssistantTurn) as $adapter) {
            $schema = $adapter->schema(new JsonSchemaTypeFactory);
            $definition = $schema === [] ? ['properties' => []] : (new ObjectSchema($schema))->toSchema();

            $this->assertIsArray($definition['properties'], $adapter->name());
            $this->assertNotSame('', $adapter->description(), $adapter->name());
        }
    }
}
