<?php

declare(strict_types=1);

namespace Botovis\Laravel\Llm;

use Botovis\Core\Contracts\LlmDriverInterface;
use Botovis\Core\DTO\LlmResponse;

/**
 * Anthropic (Claude) LLM Driver.
 *
 * Supports native tool calling via Anthropic's tool_use API.
 */
class AnthropicDriver implements LlmDriverInterface
{
    public function __construct(
        private readonly string $apiKey,
        private readonly string $model = 'claude-sonnet-4-20250514',
    ) {}

    public function name(): string
    {
        return 'anthropic';
    }

    public function chat(string $systemPrompt, array $messages): string
    {
        $payload = [
            'model' => $this->model,
            'max_tokens' => 2048,
            'system' => $systemPrompt,
            'messages' => $messages,
        ];

        $response = $this->request($payload);

        return $response['content'][0]['text'] ?? '';
    }

    /**
     * Chat with native tool calling support.
     *
     * Anthropic tool use flow:
     * 1. Send request with `tools` array → LLM responds with `tool_use` content block
     * 2. Send `tool_result` messages back → LLM continues reasoning
     *
     * @param array $tools OpenAI-format tool defs — automatically converted to Anthropic format
     */
    public function chatWithTools(string $systemPrompt, array $messages, array $tools): LlmResponse
    {
        $anthropicMessages = $this->convertMessages($messages);

        $payload = [
            'model'      => $this->model,
            'max_tokens' => 2048,
            'system'     => $systemPrompt,
            'messages'   => $anthropicMessages,
        ];

        // Only include tools when available (omit for generate stopping)
        if (!empty($tools)) {
            $payload['tools'] = $this->convertToolDefinitions($tools);
        }

        $response = $this->request($payload);

        return $this->parseLlmResponse($response);
    }

    public function stream(string $systemPrompt, array $messages, callable $onToken): string
    {
        $payload = [
            'model' => $this->model,
            'max_tokens' => 2048,
            'system' => $systemPrompt,
            'messages' => $messages,
            'stream' => true,
        ];

        return $this->requestStream($payload, $onToken);
    }

    /**
     * Convert OpenAI-format tool definitions to Anthropic format.
     *
     * OpenAI: [{ type: "function", function: { name, description, parameters } }]
     * Anthropic: [{ name, description, input_schema }]
     */
    private function convertToolDefinitions(array $tools): array
    {
        return array_map(function (array $tool) {
            $fn = $tool['function'] ?? $tool;
            return [
                'name'         => $fn['name'],
                'description'  => $fn['description'] ?? '',
                'input_schema' => $fn['parameters'] ?? ['type' => 'object', 'properties' => new \stdClass()],
            ];
        }, $tools);
    }

    /**
     * Convert normalized tool messages to Anthropic API format.
     *
     * Handles merging consecutive same-role messages (required by Anthropic API).
     * When parallel tool calls produce multiple consecutive assistant/tool_result messages,
     * they are merged into single messages with multiple content blocks.
     */
    private function convertMessages(array $messages): array
    {
        $result = [];

        foreach ($messages as $msg) {
            if (isset($msg['tool_call'])) {
                // Assistant tool call → Anthropic content blocks
                $content = [];
                if (!empty($msg['content'])) {
                    $content[] = ['type' => 'text', 'text' => $msg['content']];
                }
                $content[] = [
                    'type'  => 'tool_use',
                    'id'    => $msg['tool_call']['id'],
                    'name'  => $msg['tool_call']['name'],
                    'input' => !empty($msg['tool_call']['params']) ? $msg['tool_call']['params'] : new \stdClass(),
                ];

                // Merge with previous assistant message if consecutive
                $lastIdx = count($result) - 1;
                if ($lastIdx >= 0 && ($result[$lastIdx]['role'] ?? '') === 'assistant' && is_array($result[$lastIdx]['content'] ?? null)) {
                    $result[$lastIdx]['content'] = array_merge($result[$lastIdx]['content'], $content);
                } else {
                    $result[] = ['role' => 'assistant', 'content' => $content];
                }

            } elseif (($msg['role'] ?? '') === 'tool_result') {
                // Tool result → Anthropic user message with tool_result block
                $block = [
                    'type'        => 'tool_result',
                    'tool_use_id' => $msg['tool_call_id'],
                    'content'     => $msg['content'],
                ];

                // Merge with previous user/tool_result message if consecutive
                $lastIdx = count($result) - 1;
                if ($lastIdx >= 0 && ($result[$lastIdx]['role'] ?? '') === 'user' && is_array($result[$lastIdx]['content'] ?? null)) {
                    $result[$lastIdx]['content'][] = $block;
                } else {
                    $result[] = ['role' => 'user', 'content' => [$block]];
                }

            } else {
                // Standard user/assistant message — pass through
                $result[] = $msg;
            }
        }

        return $result;
    }

    /**
     * Parse Anthropic API response into structured LlmResponse.
     *
     * Anthropic returns content blocks. We look for:
     * - Multiple `tool_use` blocks → LlmResponse::parallelToolCalls
     * - Single `tool_use` block   → LlmResponse::toolCall
     * - `text` block only         → LlmResponse::text
     */
    private function parseLlmResponse(array $response): LlmResponse
    {
        $content = $response['content'] ?? [];
        $textParts = [];
        $toolUseBlocks = [];

        foreach ($content as $block) {
            if ($block['type'] === 'text') {
                $textParts[] = $block['text'];
            } elseif ($block['type'] === 'tool_use') {
                $toolUseBlocks[] = $block;
            }
        }

        $thought = !empty($textParts) ? implode("\n", $textParts) : null;

        // Multiple tool calls → parallel
        if (count($toolUseBlocks) > 1) {
            $calls = array_map(fn($b) => [
                'id'     => $b['id'],
                'name'   => $b['name'],
                'params' => $b['input'] ?? [],
            ], $toolUseBlocks);

            return LlmResponse::parallelToolCalls($calls, $thought);
        }

        // Single tool call
        if (count($toolUseBlocks) === 1) {
            $tc = $toolUseBlocks[0];
            return LlmResponse::toolCall(
                toolName: $tc['name'],
                toolParams: $tc['input'] ?? [],
                toolCallId: $tc['id'],
                thought: $thought,
            );
        }

        // Text-only response (final answer)
        return LlmResponse::text($thought ?? '');
    }

    private function request(array $payload): array
    {
        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL => 'https://api.anthropic.com/v1/messages',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                "x-api-key: {$this->apiKey}",
                'anthropic-version: 2023-06-01',
            ],
            CURLOPT_TIMEOUT => 60,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new \RuntimeException("Botovis Anthropic request failed: {$error}");
        }

        $decoded = json_decode($response, true);

        if ($httpCode !== 200) {
            $errorMsg = $decoded['error']['message'] ?? "HTTP {$httpCode}";
            throw new \RuntimeException("Botovis Anthropic API error: {$errorMsg}");
        }

        return $decoded;
    }

    private function requestStream(array $payload, callable $onToken): string
    {
        $ch = curl_init();
        $fullResponse = '';

        curl_setopt_array($ch, [
            CURLOPT_URL => 'https://api.anthropic.com/v1/messages',
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                "x-api-key: {$this->apiKey}",
                'anthropic-version: 2023-06-01',
            ],
            CURLOPT_TIMEOUT => 120,
            CURLOPT_WRITEFUNCTION => function ($ch, $data) use ($onToken, &$fullResponse) {
                $lines = explode("\n", $data);
                foreach ($lines as $line) {
                    $line = trim($line);
                    if (!str_starts_with($line, 'data: ')) {
                        continue;
                    }

                    $json = substr($line, 6);
                    $parsed = json_decode($json, true);

                    if (($parsed['type'] ?? '') === 'content_block_delta') {
                        $token = $parsed['delta']['text'] ?? '';
                        if ($token !== '') {
                            $fullResponse .= $token;
                            $onToken($token);
                        }
                    }
                }
                return strlen($data);
            },
        ]);

        curl_exec($ch);
        curl_close($ch);

        return $fullResponse;
    }
}
