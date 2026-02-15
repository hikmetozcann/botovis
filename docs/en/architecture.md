# Architecture

This document describes the internal architecture of Botovis for developers who want to understand, extend, or contribute to the project.

## Package Structure

Botovis is a monorepo with three packages:

```
botovis/
├── packages/
│   ├── core/           # Framework-agnostic PHP (contracts, DTOs, agent logic)
│   ├── laravel/        # Laravel integration (drivers, tools, controllers)
│   └── widget/         # TypeScript Web Component (zero dependencies)
├── docs/               # Documentation
└── tests/              # Shared test fixtures
```

### `core` — Framework-Agnostic PHP

Contains all contracts (interfaces), DTOs, enums, schema models, the agent loop, and orchestration logic. **No framework dependencies** — depends only on PHP 8.1+.

```
core/src/
├── Contracts/           # 8 interfaces (LlmDriver, SchemaDiscovery, ActionExecutor, etc.)
├── DTO/                 # Data objects (LlmResponse, SecurityContext, Conversation, Message)
├── Enums/               # ActionType, ColumnType, IntentType, RelationType
├── Schema/              # DatabaseSchema, TableSchema, ColumnSchema, RelationSchema
├── Tools/               # ToolInterface, ToolRegistry, ToolResult
├── Intent/              # IntentResolver, ResolvedIntent (simple mode)
├── Conversation/        # ConversationState
├── Agent/               # AgentLoop, AgentOrchestrator, AgentState, AgentStep, StreamingEvent
├── Orchestrator.php     # Simple-mode orchestrator
└── OrchestratorResponse.php
```

### `laravel` — Laravel Integration

Implements core interfaces using Laravel's Eloquent, Cache, Session, Auth, etc.

```
laravel/src/
├── BotovisServiceProvider.php    # DI bindings, publishes, routes, Blade directive
├── Http/
│   ├── BotovisController.php     # Chat + SSE streaming endpoints
│   └── ConversationController.php # Conversation CRUD
├── Llm/
│   ├── LlmDriverFactory.php      # Creates driver from config
│   ├── AnthropicDriver.php        # Claude API
│   ├── OpenAiDriver.php           # OpenAI API (+ compatible endpoints)
│   └── OllamaDriver.php          # Local Ollama
├── Tools/
│   ├── BaseTool.php               # Shared Eloquent helpers
│   ├── SearchRecordsTool.php      # 8 database tools...
│   └── ...
├── Schema/
│   └── EloquentSchemaDiscovery.php  # Model reflection + DB introspection
├── Action/
│   └── EloquentActionExecutor.php   # Eloquent CRUD execution
├── Security/
│   └── BotovisAuthorizer.php        # Role-based auth
├── Conversation/
│   └── CacheConversationManager.php # Cache-based state
├── Repositories/
│   ├── EloquentConversationRepository.php  # Database storage
│   └── SessionConversationRepository.php   # Session storage
├── Models/
│   ├── BotovisConversation.php
│   └── BotovisMessage.php
└── Commands/
    ├── DiscoverCommand.php
    └── ChatCommand.php
```

### `widget` — TypeScript Web Component

Zero-dependency chat UI built as a Web Component with Shadow DOM.

```
widget/src/
├── index.ts           # Custom element registration, exports
├── botovis-chat.ts    # Main component (1600+ lines)
├── api.ts             # REST + SSE client with CSRF handling
├── types.ts           # All TypeScript interfaces
├── styles.ts          # Complete CSS (Shadow DOM adopted stylesheets)
├── i18n.ts            # Turkish + English translations (68 keys)
└── icons.ts           # 30+ inline SVG icons
```

Build output: ES module, UMD, and IIFE formats via Vite.

## Request Lifecycle

### Agent Mode (default)

```
Browser                Widget               Laravel              AgentLoop
  │                      │                     │                     │
  │  Click send          │                     │                     │
  │─────────────────────>│                     │                     │
  │                      │  POST /stream       │                     │
  │                      │────────────────────>│                     │
  │                      │                     │  Build security ctx │
  │                      │                     │  Resolve user role  │
  │                      │                     │  Get conv. history  │
  │                      │                     │────────────────────>│
  │                      │                     │                     │  Build system prompt
  │                      │                     │                     │  (schema + rules + permissions)
  │                      │                     │                     │
  │                      │                     │                     │  LOOP:
  │                      │                     │                     │  ┌──────────────────────────┐
  │                      │                     │                     │  │ LLM.chatWithTools()      │
  │  SSE: step           │                     │  yield step event   │  │                          │
  │<─────────────────────│<────────────────────│<────────────────────│  │ → text? → complete       │
  │                      │                     │                     │  │ → tool_call? → execute   │
  │                      │                     │                     │  │   → read: run + observe  │
  │  SSE: step (w/obs)   │                     │  yield step event   │  │   → write: pause + ask   │
  │<─────────────────────│<────────────────────│<────────────────────│  │                          │
  │                      │                     │                     │  └──────────────────────────┘
  │                      │                     │                     │
  │  SSE: message        │                     │  yield message      │
  │<─────────────────────│<────────────────────│<────────────────────│
  │  SSE: done           │                     │                     │
  │<─────────────────────│<────────────────────│                     │
```

### Confirmation Flow

```
Agent detects write tool
    │
    ├── Adds tool_call message to state (with thought)
    ├── Adds [PENDING] tool_result placeholder
    ├── Yields SSE confirmation event
    └── Pauses loop (AgentState = needs_confirmation)

User clicks Confirm
    │
    ├── POST /stream-confirm
    ├── Execute the tool
    ├── Replace [PENDING] with actual result
    ├── Resume agent loop (so LLM can summarize)
    └── Yield message + done events
```

## Agent Loop

The `AgentLoop` implements the ReAct pattern:

### Single Step

```php
// 1. Build prompt with schema, tools, permissions, urgency warnings
$systemPrompt = $this->buildSystemPrompt($state);

// 2. Generate stopping: remove tools on last step
$toolDefs = $stepsRemaining <= 1 ? [] : $this->tools->toFunctionDefinitions();

// 3. Call LLM with native tool calling
$response = $this->llm->chatWithTools($systemPrompt, $messages, $toolDefs);

// 4. Handle response
if ($response->isText()) → complete with answer
if ($response->isToolCall()) → handleToolCalls()
```

### Parallel Tool Calls

When the LLM returns multiple tool calls in a single response:

```php
// handleToolCalls():
// 1. Add ALL tool_call messages first (API requires balanced pairs)
foreach ($toolCalls as $tc) {
    $state->addToolCallMessage($tc['id'], $tc['name'], $tc['params']);
}

// 2. Process each: read → execute, write → [PENDING]
foreach ($toolCalls as $tc) {
    if ($tool->requiresConfirmation()) {
        $state->addToolResultMessage($tc['id'], '[PENDING]...');
    } else {
        $result = $this->tools->execute($tc['name'], $tc['params']);
        $state->addToolResultMessage($tc['id'], $result->toObservation());
    }
}

// 3. All results count as ONE step
$step = AgentStep::action($stepNum, $thought, "count_records, count_records, count_records", ...);
```

### Generate Stopping

Prevents "max steps reached" with no answer:

1. **Urgency warnings** — At ≤3 steps remaining, the system prompt includes a WARNING
2. **Critical message** — At ≤1 step remaining, a CRITICAL message forces the AI to answer now
3. **Tool removal** — On the last step, tools are removed from the API call, forcing a text response

## LLM Driver Architecture

All drivers implement `LlmDriverInterface`:

```php
interface LlmDriverInterface
{
    public function chat(string $systemPrompt, array $messages): string;
    public function chatWithTools(string $systemPrompt, array $messages, array $tools): LlmResponse;
    public function stream(string $systemPrompt, array $messages, callable $onToken): string;
    public function name(): string;
}
```

### Normalized Message Format

Internally, Botovis uses a normalized message format independent of any LLM API:

```php
// Tool call (from LLM)
['role' => 'assistant', 'content' => 'thought...', 'tool_call' => [
    'id' => 'call_123',
    'name' => 'count_records',
    'params' => ['table' => 'products'],
]]

// Tool result (observation)
['role' => 'tool_result', 'tool_call_id' => 'call_123', 'content' => 'Count: 247']
```

Each driver's `convertMessages()` method translates this to the API-specific format:

- **Anthropic**: Content blocks with `tool_use` / `tool_result` types, merged consecutive same-role messages
- **OpenAI**: `tool_calls` array on assistant messages, separate `tool` role messages
- **Ollama**: Similar to OpenAI with synthetic tool call IDs

### Message Merging

Anthropic's API disallows consecutive same-role messages. The driver automatically merges:
- Consecutive assistant messages → single message with combined content blocks
- Consecutive tool_result messages → single user message with multiple `tool_result` blocks

## Schema Discovery

`EloquentSchemaDiscovery` builds the database schema by combining:

1. **Eloquent reflection** — Fillable/guarded, casts, relationships
2. **Database introspection** — Column types, nullable, defaults, max length, enum values
3. **Convention-based discovery** — Enum values from static methods (`statusOptions()`, `statusLabels()`, etc.)
4. **Label generation** — `ProductCategory` model → "Product Categories" label

The resulting `DatabaseSchema` is:
- Sent to the LLM as system prompt context
- Filtered by user permissions before display
- Used by tools to validate table names and columns

## Extension Points

### Custom LLM Driver

Implement `LlmDriverInterface` and register in the service provider:

```php
$this->app->singleton(LlmDriverInterface::class, fn() => new MyCustomDriver());
```

### Custom Tools

Implement `ToolInterface` and register:

```php
$this->app->afterResolving(ToolRegistry::class, function ($registry) {
    $registry->register(new MyCustomTool());
});
```

### Custom Auth

Implement `AuthorizerInterface` for fully custom authorization:

```php
$this->app->singleton(AuthorizerInterface::class, fn() => new MyAuthorizer());
```

### Custom Conversation Storage

Implement `ConversationRepositoryInterface`:

```php
$this->app->singleton(ConversationRepositoryInterface::class, fn() => new RedisConversationRepository());
```

## Service Container Bindings

The `BotovisServiceProvider` registers these singletons:

| Interface | Default Implementation |
|-----------|----------------------|
| `SchemaDiscoveryInterface` | `EloquentSchemaDiscovery` |
| `LlmDriverInterface` | `LlmDriverFactory::make()` |
| `ActionExecutorInterface` | `EloquentActionExecutor` |
| `ConversationManagerInterface` | `CacheConversationManager` |
| `ConversationRepositoryInterface` | `EloquentConversationRepository` |
| `BotovisAuthorizer` | `BotovisAuthorizer` |
| `ToolRegistry` | Configured with 8 built-in tools |
| `Orchestrator` | Simple mode orchestrator |
| `AgentOrchestrator` | Agent mode orchestrator |

## Blade Directive

`@botovisWidget` renders the widget view with defaults:

```php
@botovisWidget
// Expands to:
// endpoint: /{prefix}
// lang: config('botovis.locale')
// theme: 'auto'
// position: 'bottom-right'
// streaming: config('botovis.agent.streaming')
```

Override by passing an array:

```php
@botovisWidget(['theme' => 'dark', 'lang' => 'en'])
```

## Asset Publishing

| Tag | Files | Destination |
|-----|-------|-------------|
| `botovis-config` | `config/botovis.php` | `config/` |
| `botovis-assets` | Widget JS bundle | `public/vendor/botovis/` |
| `botovis-views` | Blade templates | `resources/views/vendor/botovis/` |
| `botovis-migrations` | Migration files | `database/migrations/` |

---

Previous: [Artisan Commands](artisan-commands.md)
