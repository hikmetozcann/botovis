<?php

declare(strict_types=1);

namespace Botovis\Core\Intent;

use Botovis\Core\Contracts\LlmDriverInterface;
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
    public function __construct(
        private readonly LlmDriverInterface $llm,
        private readonly DatabaseSchema $schema,
    ) {}

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

        return <<<PROMPT
You are Botovis, an AI assistant embedded in a web application. Your job is to understand user requests and convert them into structured database operations.

RULES:
1. You can ONLY operate on the tables listed below. If the user asks about a table not listed, say you don't have access.
2. You can ONLY perform the allowed actions for each table. If CREATE is not allowed, you cannot create records.
3. Always respond with VALID JSON only. No extra text before or after the JSON.
4. Column names and table names must match EXACTLY as listed in the schema.
5. For UPDATE and DELETE, you MUST include "where" conditions to identify the record(s).
6. If user's request is ambiguous or missing required information, ask for clarification.
7. Use the "fillable" columns only for CREATE/UPDATE data — never write to non-fillable columns.
8. AUTONOMOUS MULTI-STEP: If the user's request requires looking up data first before performing an action
   (e.g. "find the manager position and assign it to Yusuf"), you MUST set "auto_continue": true on the
   READ step. The system will execute the READ, feed the results back to you, and you should immediately
   proceed with the next action using the data you found. Do NOT ask the user to confirm READ prerequisites
   or say "shall I check?". Just do it.
   Example: "Yusuf'u müdür yap" → First READ positions where name=Müdür with auto_continue:true,
   then when you see the result, respond with UPDATE employees set position_id to the found ID.

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

For questions/help:
```json
{
  "type": "question",
  "message": "Your answer to the user's question",
  "confidence": 1.0
}
```

When you need more info:
```json
{
  "type": "clarification",
  "message": "What you need to know from the user",
  "confidence": 0.0
}
```

IMPORTANT: Respond ONLY with the JSON object. No markdown, no extra text.
PROMPT;
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
            return new ResolvedIntent(
                type: IntentType::UNKNOWN,
                message: "Yanıtı anlayamadım. Lütfen tekrar deneyin. (Raw: {$response})",
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
