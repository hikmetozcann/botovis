# Installation

## Requirements

- **PHP** ≥ 8.1
- **Laravel** 10, 11, or 12
- **LLM Provider** — one of:
  - [OpenAI](https://platform.openai.com/) API key
  - [Anthropic](https://console.anthropic.com/) API key (recommended)
  - [Ollama](https://ollama.ai/) running locally

## Step 1: Install the Package

```bash
composer require botovis/botovis
```

Laravel's auto-discovery will register the service provider automatically.

## Step 2: Publish Configuration

```bash
php artisan vendor:publish --tag=botovis-config
```

This creates `config/botovis.php` with all available settings.

## Step 3: Publish Widget Assets

```bash
php artisan vendor:publish --tag=botovis-assets
```

This copies the JavaScript widget to `public/vendor/botovis/`.

## Step 4: Run Migrations

```bash
php artisan migrate
```

This creates two tables:
- `botovis_conversations` — Conversation threads
- `botovis_messages` — Individual messages with metadata

> **Tip:** If you prefer session-based storage (no database tables), set `BOTOVIS_CONVERSATION_DRIVER=session` in your `.env`.

## Step 5: Configure LLM

Add your LLM API key to `.env`:

### Anthropic (Recommended)

```env
BOTOVIS_LLM_DRIVER=anthropic
BOTOVIS_ANTHROPIC_API_KEY=sk-ant-api03-...
```

Default model: `claude-sonnet-4-20250514`. Override with `BOTOVIS_ANTHROPIC_MODEL`.

### OpenAI

```env
BOTOVIS_LLM_DRIVER=openai
BOTOVIS_OPENAI_API_KEY=sk-...
```

Default model: `gpt-4o`. Override with `BOTOVIS_OPENAI_MODEL`.

Supports custom base URLs for Azure OpenAI or compatible providers:
```env
BOTOVIS_OPENAI_BASE_URL=https://your-endpoint.openai.azure.com/v1
```

### Ollama (Local)

```env
BOTOVIS_LLM_DRIVER=ollama
BOTOVIS_OLLAMA_MODEL=llama3
BOTOVIS_OLLAMA_BASE_URL=http://localhost:11434
```

Requires Ollama 0.3+ for tool calling support.

## Step 6: Register Your Models

In `config/botovis.php`, whitelist the Eloquent models Botovis can access:

```php
'models' => [
    App\Models\Product::class  => ['create', 'read', 'update', 'delete'],
    App\Models\Category::class => ['read'],
    App\Models\Order::class    => ['read'],
    App\Models\Customer::class => ['read', 'update'],
],
```

**Only whitelisted models are visible.** Models not listed here are completely invisible to Botovis and the AI.

Each model is mapped to an array of allowed actions:
- `create` — Allow creating new records
- `read` — Allow searching, counting, sampling, aggregating
- `update` — Allow modifying existing records
- `delete` — Allow deleting records

## Step 7: Add the Widget

In your Blade layout (e.g., `resources/views/layouts/app.blade.php`):

```blade
@botovisWidget
```

Or with custom options:

```blade
@botovisWidget(['theme' => 'dark', 'lang' => 'tr'])
```

## Verification

### Scan & Configure Models

```bash
php artisan botovis:models
```

Interactively select which Eloquent models Botovis should access and what permissions to grant. The command outputs a ready-to-paste config snippet. Use `--write` to update `config/botovis.php` directly.

### Check Schema Discovery

```bash
php artisan botovis:discover
```

You should see a table listing all registered models with their columns, relationships, and allowed actions.

### Test in Terminal

```bash
php artisan botovis:chat
```

This starts an interactive chat session. Try:
- "How many products are there?"
- "Show me the first 5 orders"
- "What are the distinct values of the status column in orders?"

### Check Status Endpoint

```
GET /botovis/status
```

Returns:
```json
{
  "status": "ok",
  "version": "0.1.0",
  "mode": "agent",
  "authenticated": true
}
```

## Troubleshooting

### "Route not found" or 404

Make sure the middleware matches your app. If you use `api` middleware instead of `web`:

```php
// config/botovis.php
'route' => [
    'prefix' => 'botovis',
    'middleware' => ['api', 'auth:sanctum'], // adjust to your setup
],
```

### "Unauthenticated" (401)

By default, Botovis requires authentication. For testing without auth:

```php
'security' => [
    'require_auth' => false,
],
```

### Widget not showing

1. Verify `public/vendor/botovis/botovis-widget.iife.js` exists
2. Check browser console for JavaScript errors
3. Ensure `@botovisWidget` is inside your `<body>` tag

### LLM errors

- Check your API key is correct in `.env`
- Verify network connectivity to the API endpoint
- Check Laravel logs: `storage/logs/laravel.log`
- For Ollama, ensure the service is running: `ollama list`

---

Next: [Configuration](configuration.md)
