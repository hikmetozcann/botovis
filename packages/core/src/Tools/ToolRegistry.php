<?php

declare(strict_types=1);

namespace Botovis\Core\Tools;

/**
 * Registry for available tools.
 *
 * The agent queries this registry to know what tools are available
 * and how to use them.
 */
class ToolRegistry
{
    /** @var array<string, ToolInterface> */
    private array $tools = [];

    /**
     * Register a tool.
     */
    public function register(ToolInterface $tool): self
    {
        $this->tools[$tool->name()] = $tool;
        return $this;
    }

    /**
     * Register multiple tools at once.
     *
     * @param ToolInterface[] $tools
     */
    public function registerMany(array $tools): self
    {
        foreach ($tools as $tool) {
            $this->register($tool);
        }
        return $this;
    }

    /**
     * Get a tool by name.
     */
    public function get(string $name): ?ToolInterface
    {
        return $this->tools[$name] ?? null;
    }

    /**
     * Check if a tool exists.
     */
    public function has(string $name): bool
    {
        return isset($this->tools[$name]);
    }

    /**
     * Get all registered tools.
     *
     * @return array<string, ToolInterface>
     */
    public function all(): array
    {
        return $this->tools;
    }

    /**
     * Get tool definitions for LLM (OpenAI function calling format).
     *
     * @return array[]
     */
    public function toFunctionDefinitions(): array
    {
        $definitions = [];

        foreach ($this->tools as $tool) {
            $definitions[] = [
                'type' => 'function',
                'function' => [
                    'name' => $tool->name(),
                    'description' => $tool->description(),
                    'parameters' => $tool->parameters(),
                ],
            ];
        }

        return $definitions;
    }

    /**
     * Get tool descriptions for prompt context (non-function-calling models).
     */
    public function toPromptContext(): string
    {
        $lines = ["AVAILABLE TOOLS:"];

        foreach ($this->tools as $tool) {
            $lines[] = "";
            $lines[] = "**{$tool->name()}**";
            $lines[] = $tool->description();
            
            $params = $tool->parameters();
            if (!empty($params['properties'])) {
                $lines[] = "Parameters:";
                foreach ($params['properties'] as $name => $schema) {
                    $type = $schema['type'] ?? 'any';
                    $desc = $schema['description'] ?? '';
                    $required = in_array($name, $params['required'] ?? [], true) ? ' (required)' : '';
                    $lines[] = "  - {$name}: {$type}{$required} - {$desc}";
                }
            }
        }

        return implode("\n", $lines);
    }

    /**
     * Execute a tool by name with params.
     */
    public function execute(string $name, array $params): ToolResult
    {
        $tool = $this->get($name);

        if (!$tool) {
            return ToolResult::fail("Unknown tool: {$name}");
        }

        try {
            return $tool->execute($params);
        } catch (\Throwable $e) {
            return ToolResult::fail("Tool execution failed: {$e->getMessage()}");
        }
    }
}
