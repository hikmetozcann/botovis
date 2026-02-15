# API Reference

All endpoints are prefixed with the configured route prefix (default: `/botovis`).

Default middleware: `['web']` (configurable in `config/botovis.php → route.middleware`).

## Chat Endpoints

### POST `/chat`

Send a message and get a response (non-streaming).

**Request:**
```json
{
  "message": "How many products are there?",
  "conversation_id": "optional-uuid"
}
```

**Response:**
```json
{
  "conversation_id": "uuid",
  "type": "message",
  "message": "There are 247 products in the database.",
  "steps": []
}
```

**Response types:**

| Type | Description |
|------|-------------|
| `message` | Text response with the answer |
| `confirmation` | Write operation detected, awaiting confirmation |
| `executed` | Confirmed operation was executed |
| `rejected` | User rejected the operation |
| `error` | Something went wrong |

**Confirmation response:**
```json
{
  "conversation_id": "uuid",
  "type": "confirmation",
  "message": "I'll create a new product...",
  "pending_action": {
    "action": "create_record",
    "params": {"table": "products", "data": {"name": "Widget"}},
    "description": "Create new product..."
  }
}
```

### POST `/confirm`

Confirm a pending write operation.

**Request:**
```json
{
  "conversation_id": "uuid"
}
```

**Response:**
```json
{
  "conversation_id": "uuid",
  "type": "message",
  "message": "Product created successfully! ..."
}
```

### POST `/reject`

Reject a pending write operation.

**Request:**
```json
{
  "conversation_id": "uuid"
}
```

### POST `/reset`

Clear conversation state (pending actions, agent state).

**Request:**
```json
{
  "conversation_id": "uuid"
}
```

### GET `/schema`

Get the database schema visible to the current user.

**Response:**
```json
{
  "tables": [
    {
      "table": "products",
      "label": "Products",
      "actions": ["create", "read", "update", "delete"],
      "columns": [
        {"name": "id", "type": "integer"},
        {"name": "name", "type": "string"},
        {"name": "price", "type": "decimal"},
        {"name": "status", "type": "enum", "enum_values": ["active", "draft"]}
      ],
      "relations": [
        {"name": "category", "type": "belongs_to", "related_table": "categories"}
      ]
    }
  ],
  "user": {
    "id": 1,
    "role": "admin",
    "name": "John"
  }
}
```

### GET `/status`

Health check endpoint.

**Response:**
```json
{
  "status": "ok",
  "version": "0.1.0",
  "mode": "agent",
  "authenticated": true,
  "user_role": "admin"
}
```

## Streaming Endpoints (SSE)

### POST `/stream`

Send a message with Server-Sent Events streaming.

**Request:** Same as `/chat`.

**SSE Events:**

```
event: init
data: {"conversation_id":"uuid"}

event: step
data: {"step":1,"thought":"I need to count products","action":"count_records","action_params":{"table":"products"}}

event: step
data: {"step":1,"thought":"I need to count products","action":"count_records","action_params":{"table":"products"},"observation":"Count: 247"}

event: message
data: {"content":"There are 247 products."}

event: done
data: {"steps":[...],"final_message":"There are 247 products."}
```

**Event types:**

| Event | Data | Description |
|-------|------|-------------|
| `init` | `{conversation_id}` | Stream started, conversation ID assigned |
| `step` | `AgentStep` | Agent performed a reasoning step |
| `thinking` | `{step, thought}` | Agent is thinking |
| `tool_call` | `{step, tool, params}` | Agent is calling a tool |
| `tool_result` | `{step, tool, observation}` | Tool returned a result |
| `confirmation` | `{action, params, description}` | Write operation needs confirmation |
| `message` | `{content}` | Final text response |
| `error` | `{message}` | Error occurred |
| `done` | `{steps, final_message}` | Stream complete |

### POST `/stream-confirm`

Execute a confirmed operation with SSE streaming.

**Request:**
```json
{
  "conversation_id": "uuid"
}
```

Events are the same as `/stream` — shows the execution step and the AI's summary.

## Conversation Endpoints

### GET `/conversations`

List conversations for the current user.

**Query parameters:**
- `limit` (int, default: 50)
- `offset` (int, default: 0)

**Response:**
```json
{
  "conversations": [
    {
      "id": "uuid",
      "title": "Product Report",
      "message_count": 12,
      "last_message": "There are 247 products...",
      "created_at": "2024-01-15T10:30:00Z",
      "updated_at": "2024-01-15T10:35:00Z"
    }
  ]
}
```

### POST `/conversations`

Create a new conversation.

**Request:**
```json
{
  "title": "My Chat"
}
```

### GET `/conversations/{id}`

Get a conversation with all messages.

**Response:**
```json
{
  "conversation": {
    "id": "uuid",
    "title": "Product Report",
    "messages": [
      {
        "id": "uuid",
        "role": "user",
        "content": "How many products?",
        "created_at": "2024-01-15T10:30:00Z"
      },
      {
        "id": "uuid",
        "role": "assistant",
        "content": "There are 247 products.",
        "intent": "question",
        "success": true,
        "created_at": "2024-01-15T10:30:05Z"
      }
    ],
    "created_at": "2024-01-15T10:30:00Z",
    "updated_at": "2024-01-15T10:30:05Z"
  }
}
```

### DELETE `/conversations/{id}`

Delete a conversation and all its messages. Only the owner can delete.

### PATCH `/conversations/{id}/title`

Update conversation title.

**Request:**
```json
{
  "title": "New Title"
}
```

## Error Responses

All errors return JSON with appropriate HTTP status codes:

```json
{
  "error": "Error message here"
}
```

| Status | Description |
|--------|-------------|
| 401 | Unauthenticated |
| 403 | Forbidden (insufficient permissions) |
| 404 | Conversation not found |
| 419 | CSRF token mismatch (widget auto-retries) |
| 422 | Validation error |
| 429 | Rate limited |
| 500 | Server error |

## CSRF Handling

The widget automatically handles CSRF tokens:

1. Reads `csrf-token` attribute or `<meta name="csrf-token">` tag
2. Includes `X-CSRF-TOKEN` header on all requests
3. On 419 response, re-reads the meta tag and retries once

---

Next: [Artisan Commands](artisan-commands.md) · Previous: [Widget](widget.md)
