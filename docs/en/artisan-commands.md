# Artisan Commands

Botovis provides four Artisan commands for model setup, schema inspection, interactive testing, and Telegram configuration.

## `botovis:models`

Scan your project for Eloquent models and generate the `models` configuration for `config/botovis.php`.

```bash
php artisan botovis:models
```

### How It Works

1. Scans `app/Models/` (or custom path) for all Eloquent model classes
2. Presents an interactive multi-select to choose which models to expose
3. For each model, asks what permissions to grant (Full CRUD / Read only / Read+Write / Custom)
4. Outputs a ready-to-paste config snippet

### Options

```bash
# Select all models with full CRUD permissions
php artisan botovis:models --all

# All models, read-only
php artisan botovis:models --all --read-only

# Write directly to config/botovis.php (no copy-paste needed)
php artisan botovis:models --write

# Scan a custom directory
php artisan botovis:models --path=src/Models
```

### Example Output

```
🔍 Scanning for Eloquent models...

Found 4 model(s):

  1. App\Models\User
  2. App\Models\Product
  3. App\Models\Category
  4. App\Models\Order

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
📋 Add this to your config/botovis.php:
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

    'models' => [
        \App\Models\Product::class => ['create', 'read', 'update', 'delete'],
        \App\Models\Category::class => ['read'],
        \App\Models\Order::class => ['read', 'update'],
    ],

💡 Tip: After updating config, run `php artisan botovis:discover` to verify.
```

---

## `botovis:discover`

Displays all models visible to Botovis with their schema details.

```bash
php artisan botovis:discover
```

### Output

A formatted table showing:
- Table name and model class
- Columns with types, nullable, primary key markers
- Relationships (hasMany, belongsTo, etc.)
- Allowed actions (create, read, update, delete)

### Options

```bash
# JSON output (useful for piping to other tools)
php artisan botovis:discover --json

# Show the exact text sent to the LLM as system prompt context
php artisan botovis:discover --prompt
```

### Use Cases

- **Verify setup** — Confirm Botovis sees the right models
- **Debug schema** — Check column types, enum values, relationships
- **Review prompt** — See exactly what context the AI receives
- **CI validation** — Use `--json` to programmatically verify configuration

### Example Output

```
┌─────────────┬──────────────────────────────┬────────────────────────────┐
│ Table       │ Columns                      │ Actions                    │
├─────────────┼──────────────────────────────┼────────────────────────────┤
│ products    │ id (int, PK)                 │ create, read, update,      │
│             │ name (string)                │ delete                     │
│             │ price (decimal)              │                            │
│             │ status (enum: active, draft) │                            │
│             │ category_id (int, FK)        │                            │
├─────────────┼──────────────────────────────┼────────────────────────────┤
│ categories  │ id (int, PK)                 │ read                       │
│             │ name (string)                │                            │
│             │ slug (string)                │                            │
└─────────────┴──────────────────────────────┴────────────────────────────┘
```

## `botovis:chat`

Interactive terminal REPL for testing Botovis without a browser.

```bash
php artisan botovis:chat
```

### Features

- Full agent loop with reasoning steps displayed in terminal
- Confirmation flow for write operations
- Color-coded output (thoughts, actions, observations, results)
- Conversation context maintained across messages
- Type `exit` or `quit` to end

### Options

```bash
# Use simple mode instead of agent
php artisan botovis:chat --simple
```

### Simple vs Agent Mode

| Feature | Simple | Agent |
|---------|--------|-------|
| Single query | ✅ | ✅ |
| Multi-step reasoning | ❌ | ✅ |
| Tool calling | ❌ | ✅ |
| Complex queries | Limited | Full |
| Token usage | Low | Higher |

### Example Session

```
🤖 Botovis Chat (agent mode)
Type your message (or 'exit' to quit):

You: How many products are in each category?

💭 Thinking: I need to aggregate products by category
🔧 Using: aggregate(table: products, function: count, group_by: category_id)
👁️ Result: [{category_id: 1, count: 42}, {category_id: 2, count: 18}, ...]

💭 Thinking: I should get category names too
🔧 Using: search_records(table: categories, select: [id, name])
👁️ Result: [{id: 1, name: "Electronics"}, ...]

📝 Answer:
| Category    | Products |
|-------------|----------|
| Electronics | 42       |
| Clothing    | 18       |
| Books       | 31       |

You: Create a new category called "Toys"

💭 Thinking: This is a write operation, needs confirmation
🔧 Will use: create_record(table: categories, data: {name: "Toys"})

⚠️ Confirm? Create new record in 'categories' with name='Toys' [y/N]:
```

---

## `botovis:telegram-setup`

> Requires the `botovis/botovis-telegram` package.

Set up and manage the Telegram Bot webhook for Botovis.

```bash
php artisan botovis:telegram-setup
```

### What It Does

1. Verifies your `BOTOVIS_TELEGRAM_BOT_TOKEN` by calling the Telegram `getMe` API
2. Registers the webhook URL (auto-detected from `APP_URL` + route prefix)
3. Sets up bot menu commands (`/start`, `/connect`, `/help`, `/tables`, `/reset`, `/disconnect`, `/status`)

### Options

```bash
# Custom webhook URL (if APP_URL differs from public URL)
php artisan botovis:telegram-setup --url=https://yourdomain.com/botovis/telegram/webhook

# Show current bot info & webhook status
php artisan botovis:telegram-setup --info

# Remove the webhook
php artisan botovis:telegram-setup --remove
```

### Example Output

```
🤖 Botovis Telegram Setup
━━━━━━━━━━━━━━━━━━━━━━━━━

✅ Bot verified: @YourBotName
✅ Webhook set: https://yourdomain.com/botovis/telegram/webhook
✅ Bot commands registered (7 commands)

🎉 Telegram integration is ready!

Users can link their accounts by:
  1. Opening your app panel → Telegram section → Generate Code
  2. Messaging the bot: /connect <6-digit-code>
```

---

Next: [Architecture](architecture.md) · Previous: [API Reference](api-reference.md)
