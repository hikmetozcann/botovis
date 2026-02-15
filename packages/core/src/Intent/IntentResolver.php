<?php

declare(strict_types=1);

namespace Botovis\Core\Intent;

use Botovis\Core\Contracts\LlmDriverInterface;
use Botovis\Core\DTO\SecurityContext;
use Botovis\Core\Enums\ActionType;
use Botovis\Core\Enums\IntentType;
use Botovis\Core\Schema\DatabaseSchema;

/**
 * Resolves user's natural language message into a structured intent.
 *
 * Takes the database schema + user message → sends to LLM → parses response into ResolvedIntent.
 */
class IntentResolver
{
    private ?SecurityContext $securityContext = null;

    public function __construct(
        private readonly LlmDriverInterface $llm,
        private readonly DatabaseSchema $schema,
    ) {}

    /**
     * Set security context for permission-aware prompts
     */
    public function setSecurityContext(SecurityContext $context): self
    {
        $this->securityContext = $context;
        return $this;
    }

    /**
     * Resolve a user message into a structured intent.
     *
     * @param string $userMessage  The natural language message
     * @param array  $history      Previous messages [{role, content}, ...]
     * @return ResolvedIntent
     */
    public function resolve(string $userMessage, array $history = []): ResolvedIntent
    {
        $systemPrompt = $this->buildSystemPrompt();

        $messages = array_merge($history, [
            ['role' => 'user', 'content' => $userMessage],
        ]);

        $response = $this->llm->chat($systemPrompt, $messages);

        return $this->parseResponse($response);
    }

    /**
     * Build the system prompt that tells the LLM about the database and rules.
     */
    private function buildSystemPrompt(): string
    {
        $schemaContext = $this->schema->toPromptContext();
        $userContext = $this->buildUserContext();

        return <<<PROMPT
You are Botovis, an AI assistant embedded in a web application. Your job is to understand user requests and convert them into structured database operations OR provide helpful analysis and recommendations based on data.

{$userContext}

RULES:
1. You can ONLY operate on the tables listed below. If the user asks about a table not listed, say you don't have access.
2. You can ONLY perform the allowed actions for each table. If CREATE is not allowed, you cannot create records.
3. RESPECT USER PERMISSIONS: Check the user's allowed actions before suggesting any operation. If user cannot perform an action, politely explain they don't have permission.
4. Always respond with VALID JSON only. No extra text before or after the JSON.
5. Column names and table names must match EXACTLY as listed in the schema.
6. For UPDATE and DELETE, you MUST include "where" conditions to identify the record(s).
7. If user's request is ambiguous or missing required information, ask for clarification.
8. Use the "fillable" columns only for CREATE/UPDATE data — never write to non-fillable columns.
9. AUTONOMOUS MULTI-STEP: If the user's request requires looking up data first before performing an action
   (e.g. "find the admin role and assign it to user John"), you MUST set "auto_continue": true on the
   READ step. The system will execute the READ, feed the results back to you, and you should immediately
   proceed with the next action using the data you found. Do NOT ask the user to confirm READ prerequisites
   or say "shall I check?". Just do it.
   Example: "Make John an admin" → First READ roles where name=Admin with auto_continue:true,
   then when you see the result, respond with UPDATE users set role_id to the found ID.
10. ANALYSIS & RECOMMENDATIONS: When the user asks for your opinion, analysis, or recommendations based on 
    data (e.g. "what do you think about this?", "should we proceed?", "how is the performance?"),
    use type "question" and provide a thoughtful analysis in the message field. You CAN and SHOULD interpret 
    data, give opinions, and make recommendations. This is NOT a database operation — it's advice.

{$schemaContext}

RESPONSE FORMAT (always respond with this JSON structure):

For CRUD actions:
```json
{
  "type": "action",
  "action": "create|read|update|delete",
  "table": "table_name",
  "data": {"column": "value"},
  "where": {"column": "value"},
  "select": ["column1", "column2"],
  "auto_continue": false,
  "message": "Human readable description of what you'll do",
  "confidence": 0.95
}
```

NOTE on "select": For READ actions, if the user asks for specific columns (e.g. "sadece isimlerini göster", "only names and phones"), put those column names in "select" array. If user wants all columns, omit "select" or set it to []. The "data" field is ONLY for CREATE/UPDATE payloads — never put column names in "data" for READ actions.

NOTE on "auto_continue": Set to true ONLY on READ actions that are prerequisite lookups for a follow-up action the user already requested. When auto_continue is true, the system will execute the READ and immediately ask you for the next step. Do NOT set auto_continue on standalone READs (user just wants to see data) or on write actions.

For questions, analysis, opinions, and recommendations:
```json
{
  "type": "question",
  "message": "Your detailed answer, analysis, or recommendation. Use markdown for formatting: **bold**, *italic*, - bullet points, etc.",
  "confidence": 1.0
}
```

When you need more info from user:
```json
{
  "type": "clarification",
  "message": "What you need to know from the user",
  "confidence": 0.0
}
```

CRITICAL: Always respond with ONLY the JSON object. No markdown code fences, no extra text before or after.
PROMPT;
    }

    /**
     * Build user context with permissions info for the LLM
     */
    private function buildUserContext(): string
    {
        if (!$this->securityContext || $this->securityContext->isGuest()) {
            return "CURRENT USER: Guest (unauthenticated)";
        }

        $ctx = $this->securityContext;
        $lines = ["CURRENT USER:"];
        $lines[] = "- Role: " . ($ctx->userRole ?? 'unknown');
        
        if (!empty($ctx->metadata['user_name'])) {
            $lines[] = "- Name: " . $ctx->metadata['user_name'];
        }

        // List accessible tables with their allowed actions
        $tables = $ctx->getAccessibleTables();
        if (in_array('*', $tables, true)) {
            $lines[] = "- Access: Full access to all tables";
        } else {
            $lines[] = "- Accessible tables:";
            foreach ($tables as $table) {
                $actions = $ctx->getAllowedActions($table);
                $actionsStr = in_array('*', $actions, true) ? 'all' : implode(', ', $actions);
                $lines[] = "  - {$table}: {$actionsStr}";
            }
        }

        return implode("\n", $lines);
    }

    /**
     * Parse the LLM's JSON response into a ResolvedIntent.
     */
    private function parseResponse(string $response): ResolvedIntent
    {
        // Strip potential markdown code fences
        $response = trim($response);
        $response = preg_replace('/^```(?:json)?\s*/i', '', $response);
        $response = preg_replace('/\s*```$/i', '', $response);
        $response = trim($response);

        $parsed = json_decode($response, true);

        if ($parsed === null || !isset($parsed['type'])) {
            // Log raw response in debug mode
            if (function_exists('config') && config('botovis.debug', false)) {
                logger()->debug('Botovis: Could not parse LLM response as JSON', ['raw' => $response]);
            }
            
            // If response looks like a plain text answer (not JSON), treat it as a question response
            // This handles cases where LLM forgets JSON format but gives a useful answer
            $responseLen = mb_strlen($response);
            if ($responseLen > 20 && $responseLen < 3000 && !str_starts_with($response, '{')) {
                return new ResolvedIntent(
                    type: IntentType::QUESTION,
                    message: $response,
                    confidence: 0.7,
                );
            }
            
            return new ResolvedIntent(
                type: IntentType::UNKNOWN,
                message: "Yanıtı anlayamadım. Lütfen isteğinizi farklı şekilde ifade eder misiniz?",
            );
        }

        $type = IntentType::tryFrom($parsed['type'] ?? '') ?? IntentType::UNKNOWN;
        $action = isset($parsed['action']) ? ActionType::tryFrom($parsed['action']) : null;

        // Validate table exists in schema
        $table = $parsed['table'] ?? null;
        if ($table !== null && $this->schema->findTable($table) === null) {
            return new ResolvedIntent(
                type: IntentType::UNKNOWN,
                message: "'{$table}' tablosu Botovis'e tanımlı değil.",
            );
        }

        // Validate action is allowed for the table
        if ($table !== null && $action !== null) {
            $tableSchema = $this->schema->findTable($table);
            if ($tableSchema && !$tableSchema->isActionAllowed($action)) {
                return new ResolvedIntent(
                    type: IntentType::UNKNOWN,
                    message: "'{$table}' tablosunda '{$action->value}' işlemi izin verilmemiş.",
                );
            }
        }

        return new ResolvedIntent(
            type: $type,
            action: $action,
            table: $table,
            data: $parsed['data'] ?? [],
            where: $parsed['where'] ?? [],
            select: $parsed['select'] ?? [],
            message: $parsed['message'] ?? '',
            confidence: (float) ($parsed['confidence'] ?? 0.0),
            autoContinue: (bool) ($parsed['auto_continue'] ?? false),
        );
    }
}
