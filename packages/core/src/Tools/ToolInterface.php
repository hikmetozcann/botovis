<?php

declare(strict_types=1);

namespace Botovis\Core\Tools;

/**
 * Contract for tools that the AI agent can use.
 *
 * Tools are the "hands" of the agent — they let it interact with the world.
 * Each tool has a name, description, parameters schema, and execute method.
 *
 * The agent sees the tool's name/description and decides when to use it.
 */
interface ToolInterface
{
    /**
     * Unique identifier for this tool.
     * Used by LLM to reference the tool in function calls.
     *
     * @example "search_records", "count_records", "get_sample_data"
     */
    public function name(): string;

    /**
     * Human-readable description of what this tool does.
     * This is shown to the LLM to help it decide when to use the tool.
     */
    public function description(): string;

    /**
     * JSON Schema for the tool's parameters.
     * Follows OpenAI function calling format.
     *
     * @return array{type: string, properties: array, required?: array}
     *
     * @example [
     *   'type' => 'object',
     *   'properties' => [
     *     'table' => ['type' => 'string', 'description' => 'Table name'],
     *     'where' => ['type' => 'object', 'description' => 'Filter conditions'],
     *   ],
     *   'required' => ['table'],
     * ]
     */
    public function parameters(): array;

    /**
     * Execute the tool with the given parameters.
     *
     * @param array $params Parameters matching the schema
     * @return ToolResult The result of the tool execution
     */
    public function execute(array $params): ToolResult;

    /**
     * Whether this tool requires confirmation before execution.
     * Write operations (create, update, delete) typically need confirmation.
     */
    public function requiresConfirmation(): bool;
}
