# Tools

Tools are the actions the AI agent can perform. Botovis ships with 8 built-in tools for database interaction.

## Built-in Tools

### Read Tools

These tools execute immediately — no confirmation required.

#### `search_records`

Search for records with filters, sorting, and column selection.

```
Parameters:
  table      (string, required)  — Table name
  where      (object)            — Filter conditions
  select     (string[])          — Columns to return
  order_by   (string)            — Column to sort by
  order_dir  (enum: asc|desc)    — Sort direction
  limit      (integer)           — Max results (default: 20, max: 100)
```

**Where conditions** support:
- Exact match: `{"status": "active"}`
- Array (WHERE IN): `{"status": ["active", "pending"]}`
- LIKE pattern: `{"name": "%widget%"}`
- Comparison: `{"price": ">100"}`, `{"created_at": ">=2024-01-01"}`

#### `count_records`

Count records, optionally filtered.

```
Parameters:
  table   (string, required)  — Table name
  where   (object)            — Filter conditions
```

#### `get_sample_data`

Get a few sample records to understand table structure and content.

```
Parameters:
  table   (string, required)  — Table name
  limit   (integer)           — Number of samples (default: 3, max: 5)
```

#### `get_column_stats`

Get column statistics: min, max, average (numbers), distinct values (text/enum).

```
Parameters:
  table    (string, required)  — Table name
  column   (string, required)  — Column name
```

#### `aggregate`

Run aggregate functions with optional GROUP BY.

```
Parameters:
  table     (string, required)         — Table name
  function  (enum, required)           — count, sum, avg, min, max
  column    (string)                   — Column to aggregate (required for sum/avg/min/max)
  where     (object)                   — Filter conditions
  group_by  (string)                   — Column to group results by
```

### Write Tools

These tools **always require user confirmation** before execution.

#### `create_record`

Create a new record.

```
Parameters:
  table   (string, required)  — Table name
  data    (object, required)  — Column values for the new record
```

Only fillable columns are accepted. Guarded and non-fillable columns are silently filtered.

#### `update_record`

Update one or more records matching conditions.

```
Parameters:
  table   (string, required)  — Table name
  where   (object, required)  — Records to update (prevents mass updates)
  data    (object, required)  — New values
```

Records are updated individually via Eloquent (respects model events, observers, mutators).

#### `delete_record`

Delete records matching conditions. Respects SoftDeletes if the model uses it.

```
Parameters:
  table   (string, required)  — Table name
  where   (object, required)  — Records to delete (prevents mass deletion)
```

## Tool Execution Flow

```
AI decides to use a tool
    ↓
Is it a read tool? → Execute immediately → Return observation to AI
    ↓
Is it a write tool? → Pause agent loop → Show confirmation to user
    ↓
User confirms → Execute → Return result to AI for summary
User rejects  → Add rejection to context → AI acknowledges
```

## Creating Custom Tools

You can create custom tools by implementing `ToolInterface`:

```php
<?php

namespace App\Botovis\Tools;

use Botovis\Core\Tools\ToolInterface;
use Botovis\Core\Tools\ToolResult;

class GetWeatherTool implements ToolInterface
{
    public function name(): string
    {
        return 'get_weather';
    }

    public function description(): string
    {
        return 'Get current weather for a city.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'city' => [
                    'type' => 'string',
                    'description' => 'City name',
                ],
            ],
            'required' => ['city'],
        ];
    }

    public function requiresConfirmation(): bool
    {
        return false;
    }

    public function execute(array $params): ToolResult
    {
        $city = $params['city'];
        
        // Your logic here...
        $weather = WeatherService::get($city);

        return ToolResult::ok(
            "Weather in {$city}: {$weather->temp}°C, {$weather->condition}",
            ['temp' => $weather->temp, 'condition' => $weather->condition]
        );
    }
}
```

### Registering Custom Tools

In a service provider:

```php
use Botovis\Core\Tools\ToolRegistry;

public function boot(): void
{
    $this->app->afterResolving(ToolRegistry::class, function (ToolRegistry $registry) {
        $registry->register(new \App\Botovis\Tools\GetWeatherTool());
    });
}
```

### ToolResult

Tools return a `ToolResult` object:

```php
// Success
ToolResult::ok('Found 42 records', $data, ['metadata' => '...']);

// Failure
ToolResult::fail('Table not found');
```

The `toObservation()` method converts the result to a string the AI can understand:
- Success: Message + JSON-encoded data
- Failure: Error message

## Parallel Tool Calling

When the AI needs data from multiple tables, it can call multiple tools in a single step. For example, generating a system report might call `count_records` 7 times simultaneously instead of sequentially.

This is handled automatically — the agent system prompt includes:

> *"When you need data from multiple tables or multiple counts, call all the tools at once in parallel instead of one by one."*

## Tool Parameters Schema

Tool parameters follow the [JSON Schema](https://json-schema.org/) format used by OpenAI's function calling API:

```php
public function parameters(): array
{
    return [
        'type' => 'object',
        'properties' => [
            'param_name' => [
                'type' => 'string',          // string, integer, number, boolean, array, object
                'description' => '...',
                'enum' => ['a', 'b', 'c'],   // optional: restrict to specific values
                'default' => 'a',            // optional: default value
            ],
        ],
        'required' => ['param_name'],
    ];
}
```

---

Next: [Widget](widget.md) · Previous: [Security](security.md)
