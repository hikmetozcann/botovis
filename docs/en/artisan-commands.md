# Artisan Commands

Botovis provides two Artisan commands for schema inspection and interactive testing.

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

Next: [Architecture](architecture.md) · Previous: [API Reference](api-reference.md)
