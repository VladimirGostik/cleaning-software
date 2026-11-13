<?php

declare(strict_types=1);

namespace Tests\Unit\Ai;

use App\Ai\Tools\AssistantToolbox;
use App\Ai\Tools\ReportTaskOutcomeTool;
use App\Enums\ActionChannelEnum;
use App\Mcp\Servers\CleanMasterServer;
use App\Mcp\Tools\PrepareActionTool;
use App\Services\Ai\AssistantTurn;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Laravel\Ai\ObjectSchema;
use Laravel\Ai\Tools\Request;
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

        $excluded = array_map(fn (string $class): string => app($class)->name(), AssistantToolbox::EXCLUDED);

        $this->assertEqualsCanonicalizing(array_values(array_diff($this->serverToolNames(), $excluded)), $names);
        $this->assertContains('prepare_action', $names);
        $this->assertNotContains('confirm_action', $names);
        $this->assertNotContains('delete_global_task', $names);
        $this->assertNotContains('update_document_template', $names);
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

    public function test_a_task_run_gets_the_assistant_tools_minus_cross_company_and_existing_task_changes_plus_the_outcome_tool(): void
    {
        $adapters = (new AssistantToolbox)->forTaskRun(new AssistantTurn(null, ActionChannelEnum::TaskRun, 'company-id'));
        $names = array_map(ToolNameResolver::resolve(...), $adapters);

        foreach (['list_companies', 'company_overview', 'complete_task', 'update_task', 'assign_task', 'confirm_action', 'delete_global_task', 'update_document_template'] as $absent) {
            $this->assertNotContains($absent, $names, $absent);
        }

        foreach (['report_task_outcome', 'create_task', 'prepare_action', 'list_tasks'] as $present) {
            $this->assertContains($present, $names, $present);
        }

        $this->assertInstanceOf(ReportTaskOutcomeTool::class, end($adapters));
    }

    public function test_the_assistant_toolset_and_the_server_tools_are_unchanged_by_task_runs(): void
    {
        $this->assertCount(35, CleanMasterServer::TOOLS);
        $this->assertNotContains('report_task_outcome', $this->serverToolNames());
        $this->assertContains('complete_task', array_map(ToolNameResolver::resolve(...), (new AssistantToolbox)->make(new AssistantTurn)));
    }

    public function test_the_contact_tools_are_in_the_assistant_and_task_run_toolsets(): void
    {
        $assistant = array_map(ToolNameResolver::resolve(...), (new AssistantToolbox)->make(new AssistantTurn));
        $taskRun = array_map(ToolNameResolver::resolve(...), (new AssistantToolbox)->forTaskRun(new AssistantTurn(null, ActionChannelEnum::TaskRun, 'company-id')));

        foreach (['list_contacts', 'create_contact', 'update_contact'] as $name) {
            $this->assertContains($name, $this->serverToolNames(), $name);
            $this->assertContains($name, $assistant, $name);
            $this->assertContains($name, $taskRun, $name);
        }
    }

    public function test_the_outcome_tool_schema_maps_to_a_valid_provider_definition(): void
    {
        $tool = new ReportTaskOutcomeTool(new AssistantTurn);
        $definition = (new ObjectSchema($tool->schema(new JsonSchemaTypeFactory)))->toSchema();

        $this->assertSame(['outcome', 'message'], array_keys((array) $definition['properties']));
        $this->assertSame('Recorded. Stop now.', $tool->handle(new Request(['outcome' => 'done', 'message' => 'ok'])));
    }
}
