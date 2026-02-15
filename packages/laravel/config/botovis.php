<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Agent Mode
    |--------------------------------------------------------------------------
    |
    | Botovis has two operating modes:
    |
    | 'simple' - Single-shot intent resolution (faster, less tokens)
    | 'agent'  - ReAct pattern with tool use (smarter, more capable)
    |
    | Agent mode allows the AI to use tools, explore data, and reason
    | through complex queries step by step. Recommended for production.
    |
    */
    'mode' => env('BOTOVIS_MODE', 'agent'),

    /*
    |--------------------------------------------------------------------------
    | Locale / Language
    |--------------------------------------------------------------------------
    |
    | Controls the widget UI language (buttons, labels, placeholders)
    | and internal system messages. The AI assistant automatically
    | responds in the same language the user writes in.
    |
    | Supported: 'en' (English), 'tr' (Turkish)
    |
    */
    'locale' => env('BOTOVIS_LOCALE', 'en'),

    /*
    |--------------------------------------------------------------------------
    | Agent Configuration
    |--------------------------------------------------------------------------
    */
    'agent' => [
        /*
        | Maximum reasoning steps before giving up.
        | More steps = can handle more complex queries but uses more tokens.
        */
        'max_steps' => env('BOTOVIS_AGENT_MAX_STEPS', 10),

        /*
        | Show reasoning steps to user in response.
        | Useful for debugging and transparency.
        */
        'show_steps' => env('BOTOVIS_AGENT_SHOW_STEPS', false),

        /*
        | Enable streaming (Server-Sent Events).
        | When true, agent steps are sent in real-time.
        | When false, waits until complete before responding.
        */
        'streaming' => env('BOTOVIS_AGENT_STREAMING', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Botovis Models (Whitelist)
    |--------------------------------------------------------------------------
    |
    | Define which models Botovis can access and what actions are allowed.
    | Models not listed here are completely invisible to Botovis.
    |
    | Format:
    |   ModelClass::class => ['create', 'read', 'update', 'delete']
    |
    | Example:
    |   App\Models\Product::class => ['create', 'read', 'update'],
    |   App\Models\Category::class => ['read'],
    |
    */
    'models' => [
        // App\Models\Product::class => ['create', 'read', 'update', 'delete'],
    ],

    /*
    |--------------------------------------------------------------------------
    | LLM Driver
    |--------------------------------------------------------------------------
    |
    | The AI driver to use for natural language understanding.
    | Supported: "openai", "anthropic", "ollama"
    |
    */
    'llm' => [
        'driver' => env('BOTOVIS_LLM_DRIVER', 'openai'),

        'openai' => [
            'api_key' => env('BOTOVIS_OPENAI_API_KEY'),
            'model' => env('BOTOVIS_OPENAI_MODEL', 'gpt-4o'),
            'base_url' => env('BOTOVIS_OPENAI_BASE_URL', 'https://api.openai.com/v1'),
        ],

        'anthropic' => [
            'api_key' => env('BOTOVIS_ANTHROPIC_API_KEY'),
            'model' => env('BOTOVIS_ANTHROPIC_MODEL', 'claude-sonnet-4-20250514'),
        ],

        'ollama' => [
            'model' => env('BOTOVIS_OLLAMA_MODEL', 'llama3'),
            'base_url' => env('BOTOVIS_OLLAMA_BASE_URL', 'http://localhost:11434'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Security & Authorization
    |--------------------------------------------------------------------------
    |
    | Configure how Botovis handles user authentication and authorization.
    | Botovis uses your existing Laravel auth system — no separate users.
    |
    */
    'security' => [
        /*
        | Authentication guard to use (from config/auth.php)
        | Set to null to disable auth (not recommended for production)
        */
        'guard' => 'web',

        /*
        | Require authentication for all Botovis endpoints
        | If false, unauthenticated users can still use Botovis (based on permissions)
        */
        'require_auth' => true,

        /*
        | Actions that require user confirmation before executing
        | 'read' actions never require confirmation regardless of this setting
        */
        'require_confirmation' => ['create', 'update', 'delete'],

        /*
        | Use Laravel Gates/Policies for authorization
        | When enabled, checks if user can('botovis.{table}.{action}')
        */
        'use_gates' => false,

        /*
        | Role-based permissions
        | Define which tables/actions each role can access
        | Use '*' for all tables or all actions
        |
        | Format:
        |   'role_name' => [
        |       'table_name' => ['create', 'read', 'update', 'delete'],
        |       '*' => ['read'],  // all tables, read only
        |   ],
        |
        | Special roles:
        |   '*' => [...] applies to all authenticated users (default permissions)
        */
        'roles' => [
            // Example configurations:
            //
            // 'admin' => [
            //     '*' => ['create', 'read', 'update', 'delete'],
            // ],
            //
            // 'manager' => [
            //     'employees' => ['create', 'read', 'update'],
            //     'positions' => ['read', 'update'],
            //     'shift_templates' => ['read'],
            // ],
            //
            // 'user' => [
            //     '*' => ['read'],
            // ],
            //
            // Default: all authenticated users get full access
            '*' => [
                '*' => ['create', 'read', 'update', 'delete'],
            ],
        ],

        /*
        | How to determine user's role
        | Options:
        |   'attribute' - Use $user->{role_attribute} (e.g., $user->role)
        |   'method'    - Call $user->{role_method}() (e.g., $user->getRole())
        |   'spatie'    - Use Spatie Permission package ($user->getRoleNames())
        |   'callback'  - Use custom callback function
        */
        'role_resolver' => 'attribute',
        'role_attribute' => 'role',
        'role_method' => 'getRole',
        // 'role_callback' => fn($user) => $user->roles->first()?->name ?? 'user',

        /*
        | Custom authorization callback (overrides role-based if set)
        | Return true to allow, false to deny
        | fn(User $user, string $table, string $action): bool
        */
        // 'authorize_callback' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Route Configuration
    |--------------------------------------------------------------------------
    */
    'route' => [
        'prefix' => 'botovis',
        'middleware' => ['web'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Conversation History
    |--------------------------------------------------------------------------
    |
    | Configure how conversation history is stored and managed.
    |
    */
    'conversations' => [
        /*
        | Enable conversation history persistence
        | When disabled, conversations are not saved
        */
        'enabled' => true,

        /*
        | Storage driver for conversations
        | Options:
        |   'database' - Store in botovis_conversations/messages tables (requires migration)
        |   'session'  - Store in PHP session (cleared when session ends)
        */
        'driver' => env('BOTOVIS_CONVERSATION_DRIVER', 'database'),

        /*
        | Auto-generate conversation title from first message
        */
        'auto_title' => true,

        /*
        | Maximum number of messages to include as context for LLM
        | More messages = better context but higher token usage
        */
        'context_messages' => 10,

        /*
        | Maximum conversations per user (0 = unlimited)
        | Oldest conversations will be deleted when limit is reached
        */
        'max_per_user' => 0,
    ],

];
