<p align="center">
  <picture>
    <source media="(prefers-color-scheme: dark)" srcset="art/logo-dark.svg">
    <source media="(prefers-color-scheme: light)" srcset="art/logo.svg">
    <img alt="Botovis" src="art/logo.svg" width="320">
  </picture>
</p>

<p align="center">
  <strong>AI-powered natural language interface for your database.</strong><br>
  Add an intelligent chat widget to your app — users can query, analyze, and modify data just by asking.
</p>

<p align="center">
  <a href="https://github.com/hikmetozcann/botovis/actions"><img src="https://github.com/hikmetozcann/botovis/workflows/Laravel%20Tests/badge.svg" alt="Tests"></a>
  <a href="LICENSE"><img src="https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square" alt="License"></a>
</p>

<p align="center">
  <a href="#packages">Packages</a> •
  <a href="#how-it-works">How It Works</a> •
  <a href="#quick-start-laravel">Quick Start</a> •
  <a href="docs/en/README.md">Documentation</a> •
  <a href="docs/tr/README.md">Türkçe</a>
</p>

---

## What is Botovis?

Botovis is an **AI-powered database assistant** that you can drop into any application. Users interact with your database using natural language — no SQL, no dashboards, no custom forms.

```
User: "How many orders were placed last month?"
Botovis: Uses count_records tool → "There were 847 orders last month."

User: "Show me the top 5 customers by revenue"
Botovis: Uses aggregate + search tools → Displays a formatted table

User: "Create a new product called 'Widget Pro' priced at $29.99"
Botovis: Asks for confirmation → Creates the record after approval
```

### Key Features

- **Natural language CRUD** — Search, count, aggregate, create, update, delete via conversation
- **AI Agent with ReAct pattern** — Multi-step reasoning with parallel tool calling
- **Write protection** — All write operations require explicit user confirmation
- **Role-based security** — Multi-layer auth with schema filtering
- **Real-time streaming** — Server-Sent Events with live reasoning timeline
- **Multi-LLM support** — OpenAI, Anthropic (Claude), Ollama (local)
- **Zero-dependency widget** — Web Component with Shadow DOM, works everywhere
- **Conversation history** — Persistent chat threads
- **Framework wrappers** — React and Vue 3 components included
- **i18n** — English and Turkish built-in

---

## Packages

Botovis is a monorepo with a framework-agnostic core and framework-specific integrations:

| Package | Description | Status |
|---------|-------------|--------|
| [`botovis/core`](packages/core) | Contracts, DTOs, agent loop, tool system — no framework dependencies | ✅ Stable |
| [`botovis/botovis-laravel`](packages/laravel) | Laravel integration — Eloquent, Auth, Blade, Artisan | ✅ Stable |
| [`@botovis/widget`](packages/widget) | TypeScript chat widget — Web Component, zero dependencies | ✅ Stable |
| [`botovis/botovis-telegram`](packages/telegram) | Telegram Bot channel adapter — query your database from Telegram | ✅ New |
| `@botovis/node` | Node.js / Express integration | 🔜 Planned |
| `botovis/dotnet` | .NET / ASP.NET Core integration | 🔜 Planned |

### Architecture

```
┌─────────────────────────────────────────────────┐
│                  @botovis/widget                 │
│          (Web Component — any frontend)          │
└──────────────────────┬──────────────────────────┘
                       │ HTTP / SSE
┌──────────────────────┴──────────────────────────┐
│             Framework Integration                │
│     ┌──────────┐  ┌──────────┐  ┌──────────┐    │
│     │ Laravel  │  │ Node.js  │  │  .NET    │    │
│     │    ✅    │  │   🔜     │  │   🔜     │    │
│     └────┬─────┘  └────┬─────┘  └────┬─────┘    │
└──────────┼──────────────┼──────────────┼─────────┘
           │              │              │
┌──────────┴──────────────┴──────────────┴─────────┐
│                   botovis/core                    │
│  Contracts • DTOs • Agent Loop • Tool Registry    │
│  Schema Models • Security Context • LLM Interface │
└──────────┬──────────────────────────────┬─────────┘
           │                              │
┌──────────┴──────────┐  ┌────────────────┴────────┐
│  Channel Adapters   │  │   (same brain,          │
│  ┌───────────────┐  │  │    different mouth)      │
│  │  Telegram ✅  │  │  │                          │
│  │  Discord  🔜  │  │  │                          │
│  │  Slack    🔜  │  │  │                          │
│  └───────────────┘  │  │                          │
└─────────────────────┘  └─────────────────────────┘
```

---

## How It Works

Botovis uses an **AI Agent** with the [ReAct](https://arxiv.org/abs/2210.03629) pattern — it thinks, acts (calls tools), observes results, and repeats until it has an answer:

```
User → "How many orders per status?"

Step 1  Think: "I need to group orders by status"
        Act:   group_records(table: orders, group_by: status)
        Observe: [{status: pending, count: 42}, {status: shipped, count: 315}, ...]

Step 2  Think: "I have the data, I'll format a table"
        Respond: | Status | Count | ...
```

### Tools

| Tool | Type | Description |
|------|------|-------------|
| `search_records` | Read | Search with filters, sorting, column selection |
| `count_records` | Read | Count records with optional conditions |
| `aggregate_records` | Read | SUM, AVG, MIN, MAX with conditions |
| `group_records` | Read | Group by column with counts |
| `list_tables` | Read | List accessible tables and columns |
| `create_record` | **Write** | Create record *(requires confirmation)* |
| `update_record` | **Write** | Update records *(requires confirmation)* |
| `delete_record` | **Write** | Delete records *(requires confirmation)* |

### Parallel Tool Calling

The agent calls multiple tools simultaneously when possible — e.g., counting 3 tables in a single step.

### Generate Stopping

If the agent approaches its step limit, it automatically summarizes with available data instead of failing silently.

---

## Quick Start (Laravel)

### 1. Install

```bash
composer require botovis/botovis-laravel
```

### 2. Publish & Migrate

```bash
php artisan vendor:publish --tag=botovis-config
php artisan vendor:publish --tag=botovis-assets
php artisan migrate
```

### 3. Configure LLM

```env
BOTOVIS_LLM_DRIVER=anthropic
ANTHROPIC_API_KEY=sk-ant-...
```

### 4. Add the Widget

```blade
@botovisWidget
```

### 5. Verify

```bash
php artisan botovis:models     # Scan & configure models
php artisan botovis:discover   # See discovered tables
php artisan botovis:chat       # Test in terminal
```

That's it. Visit your app and click the chat button.

### Optional: Telegram Integration

```bash
composer require botovis/botovis-telegram
php artisan vendor:publish --tag=botovis-telegram-config
php artisan vendor:publish --tag=botovis-telegram-migrations
php artisan migrate
```

```env
BOTOVIS_TELEGRAM_ENABLED=true
BOTOVIS_TELEGRAM_BOT_TOKEN=123456:ABC-DEF...
BOTOVIS_TELEGRAM_WEBHOOK_SECRET=your-random-secret
```

```bash
php artisan botovis:telegram-setup
```

→ Full guide: [docs/en/installation.md](docs/en/installation.md)

---

## Configuration (Laravel)

Key settings in `config/botovis.php`:

```php
'mode'   => 'agent',           // 'agent' or 'simple'
'locale' => 'en',              // 'en' or 'tr'

'agent' => [
    'max_steps' => 30,         // Max reasoning steps
    'streaming' => true,       // SSE real-time updates
],

'llm' => [
    'driver' => 'anthropic',   // 'openai', 'anthropic', 'ollama'
],

'security' => [
    'auth' => ['enabled' => true],
    'write_confirmation' => ['enabled' => true],
    'roles' => [
        'admin'  => ['can_read' => true, 'can_write' => true,  'excluded_tables' => []],
        'viewer' => ['can_read' => true, 'can_write' => false, 'excluded_tables' => ['users']],
    ],
],
```

→ Full reference: [docs/en/configuration.md](docs/en/configuration.md)

---

## Widget

```blade
@botovisWidget(['theme' => 'dark', 'lang' => 'en', 'position' => 'bottom-left'])
```

| Attribute | Values | Default |
|-----------|--------|---------|
| `theme` | `auto`, `light`, `dark` | `auto` |
| `lang` | `en`, `tr` | `en` |
| `position` | `bottom-right`, `bottom-left` | `bottom-right` |
| `streaming` | `true`, `false` | `true` |

```javascript
// JavaScript API
const widget = document.querySelector('botovis-chat');
widget.open();
widget.send('How many products are there?');
```

→ Full guide: [docs/en/widget.md](docs/en/widget.md)

---

## Documentation

| English | Türkçe | Description |
|---------|--------|-------------|
| [Installation](docs/en/installation.md) | [Kurulum](docs/tr/installation.md) | Requirements, setup, verification |
| [Configuration](docs/en/configuration.md) | [Yapılandırma](docs/tr/configuration.md) | All config options |
| [Security](docs/en/security.md) | [Güvenlik](docs/tr/security.md) | Auth, roles, permissions |
| [Tools](docs/en/tools.md) | [Araçlar](docs/tr/tools.md) | Built-in tools, custom tools |
| [Widget](docs/en/widget.md) | [Widget](docs/tr/widget.md) | Attributes, theming, wrappers |
| [API Reference](docs/en/api-reference.md) | [API Referansı](docs/tr/api-reference.md) | HTTP endpoints |
| [Artisan Commands](docs/en/artisan-commands.md) | [Artisan Komutları](docs/tr/artisan-commands.md) | CLI tools |
| [Architecture](docs/en/architecture.md) | [Mimari](docs/tr/architecture.md) | Internals, extension points |

---

## Requirements

### Laravel Integration
- PHP ≥ 8.1
- Laravel 10, 11, or 12
- LLM API key (OpenAI, Anthropic) or local Ollama

## Contributing

Please see [CONTRIBUTING.md](CONTRIBUTING.md) for details.

## Security

If you discover a vulnerability, please see [SECURITY.md](SECURITY.md).

## License

MIT — see [LICENSE](LICENSE) for details.
