# Configuration

All configuration is in `config/botovis.php`. Every option can be overridden via environment variables.

## Mode

```php
'mode' => env('BOTOVIS_MODE', 'agent'),
```

| Value | Description |
|-------|-------------|
| `agent` | **Recommended.** ReAct agent with multi-step reasoning, tool calling, and parallel execution. Smarter, handles complex queries. |
| `simple` | Single-shot intent resolution. Faster and cheaper, but limited to simple CRUD queries. |

## Locale

```php
'locale' => env('BOTOVIS_LOCALE', 'en'),
```

Controls the widget UI language (`en` or `tr`). The AI assistant automatically responds in whatever language the user writes in, regardless of this setting.

## Agent

```php
'agent' => [
    'max_steps'  => env('BOTOVIS_AGENT_MAX_STEPS', 10),
    'show_steps' => env('BOTOVIS_AGENT_SHOW_STEPS', false),
    'streaming'  => env('BOTOVIS_AGENT_STREAMING', true),
],
```

| Key | Default | Description |
|-----|---------|-------------|
| `max_steps` | `10` | Maximum reasoning steps per query. The agent uses up to 30 internal steps (each LLM call may trigger parallel tools). Higher = handles more complex queries but uses more tokens. |
| `show_steps` | `false` | Include reasoning steps in non-streaming API responses. |
| `streaming` | `true` | Enable Server-Sent Events. When `true`, the widget shows real-time thinking, tool calls, and a reasoning timeline. |

## Models (Whitelist)

```php
'models' => [
    App\Models\Product::class  => ['create', 'read', 'update', 'delete'],
    App\Models\Category::class => ['read'],
    App\Models\Order::class    => ['read'],
],
```

**This is the most important setting.** Only models listed here are visible to Botovis. The key is the fully-qualified model class name, the value is an array of allowed CRUD actions.

Botovis discovers:
- Table name and columns (types, nullable, defaults, max length)
- Fillable/guarded attributes
- Eloquent relationships (hasOne, hasMany, belongsTo, belongsToMany, morphs)
- Enum values (from `enum` column types and static methods like `statusOptions()`)
- Eloquent casts

## LLM

```php
'llm' => [
    'driver' => env('BOTOVIS_LLM_DRIVER', 'openai'),

    'openai' => [
        'api_key'  => env('BOTOVIS_OPENAI_API_KEY'),
        'model'    => env('BOTOVIS_OPENAI_MODEL', 'gpt-4o'),
        'base_url' => env('BOTOVIS_OPENAI_BASE_URL', 'https://api.openai.com/v1'),
    ],

    'anthropic' => [
        'api_key' => env('BOTOVIS_ANTHROPIC_API_KEY'),
        'model'   => env('BOTOVIS_ANTHROPIC_MODEL', 'claude-sonnet-4-20250514'),
    ],

    'ollama' => [
        'model'    => env('BOTOVIS_OLLAMA_MODEL', 'llama3'),
        'base_url' => env('BOTOVIS_OLLAMA_BASE_URL', 'http://localhost:11434'),
    ],
],
```

### Supported Drivers

| Driver | Models | Notes |
|--------|--------|-------|
| `openai` | GPT-4o, GPT-4-turbo, GPT-3.5 | Supports custom base_url (Azure, etc.) |
| `anthropic` | Claude Sonnet 4, Claude 3.5 | Best tool calling performance |
| `ollama` | Llama 3, Mistral, etc. | Requires Ollama 0.3+ for tools |

## Security

```php
'security' => [
    'guard'                => 'web',
    'require_auth'         => true,
    'require_confirmation' => ['create', 'update', 'delete'],
    'use_gates'            => false,
    'role_resolver'        => 'attribute',
    'role_attribute'       => 'role',
    'roles'                => [
        '*' => ['*' => ['create', 'read', 'update', 'delete']],
    ],
],
```

| Key | Default | Description |
|-----|---------|-------------|
| `guard` | `'web'` | Laravel auth guard (from `config/auth.php`). |
| `require_auth` | `true` | If `true`, unauthenticated users get a 401. |
| `require_confirmation` | `['create','update','delete']` | Actions that show a confirmation dialog before execution. |
| `use_gates` | `false` | If `true`, checks Laravel Gates: `can('botovis.{table}.{action}')`. |
| `role_resolver` | `'attribute'` | How to determine user's role: `attribute`, `method`, `spatie`, or `callback`. |
| `role_attribute` | `'role'` | When `role_resolver` is `attribute`, reads `$user->{role_attribute}`. |
| `role_method` | `'getRole'` | When `role_resolver` is `method`, calls `$user->{role_method}()`. |
| `roles` | `['*' => [...]]` | Role-based permissions. See [Security](security.md). |

## Route

```php
'route' => [
    'prefix'     => 'botovis',
    'middleware'  => ['web'],
],
```

| Key | Default | Description |
|-----|---------|-------------|
| `prefix` | `'botovis'` | URL prefix. All endpoints are under `/{prefix}/`. |
| `middleware` | `['web']` | Middleware stack for all Botovis routes. |

> **Note:** If your app uses Sanctum or Passport, adjust the middleware:
> ```php
> 'middleware' => ['api', 'auth:sanctum'],
> ```

## Conversations

```php
'conversations' => [
    'enabled'          => true,
    'driver'           => env('BOTOVIS_CONVERSATION_DRIVER', 'database'),
    'auto_title'       => true,
    'context_messages' => 10,
    'max_per_user'     => 0,
],
```

| Key | Default | Description |
|-----|---------|-------------|
| `enabled` | `true` | Enable conversation persistence. |
| `driver` | `'database'` | `'database'` (requires migration) or `'session'` (ephemeral). |
| `auto_title` | `true` | Auto-generate conversation title from first message. |
| `context_messages` | `10` | Number of previous messages sent to the LLM as context. |
| `max_per_user` | `0` | Maximum conversations per user. 0 = unlimited. |

## Environment Variables Reference

```env
# Mode
BOTOVIS_MODE=agent

# Locale
BOTOVIS_LOCALE=en

# Agent
BOTOVIS_AGENT_MAX_STEPS=10
BOTOVIS_AGENT_STREAMING=true
BOTOVIS_AGENT_SHOW_STEPS=false

# LLM
BOTOVIS_LLM_DRIVER=anthropic  # openai, anthropic, ollama
BOTOVIS_OPENAI_API_KEY=
BOTOVIS_OPENAI_MODEL=gpt-4o
BOTOVIS_OPENAI_BASE_URL=https://api.openai.com/v1
BOTOVIS_ANTHROPIC_API_KEY=
BOTOVIS_ANTHROPIC_MODEL=claude-sonnet-4-20250514
BOTOVIS_OLLAMA_MODEL=llama3
BOTOVIS_OLLAMA_BASE_URL=http://localhost:11434

# Conversations
BOTOVIS_CONVERSATION_DRIVER=database  # database, session
```

---

Next: [Security](security.md) · Previous: [Installation](installation.md)
