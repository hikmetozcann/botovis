<?php

declare(strict_types=1);

namespace Botovis\Laravel\Llm;

use Botovis\Core\Contracts\LlmDriverInterface;
use Botovis\Core\DTO\LlmResponse;

/**
 * OpenAI LLM Driver.
 *
 * Communicates with OpenAI API (or any OpenAI-compatible endpoint).
 * Supports native tool/function calling.
 */
class OpenAiDriver implements LlmDriverInterface
{
    public function __construct(
        private readonly string $apiKey,
        private readonly string $model = 'gpt-4o',
        private readonly string $baseUrl = 'https://api.openai.com/v1',
    ) {}

    public function name(): string
    {
        return 'openai';
    }

    public function chat(string $systemPrompt, array $messages): string
    {
        $payload = [
            'model' => $this->model,
            'messages' => array_merge(
                [['role' => 'system', 'content' => $systemPrompt]],
                $messages,
            ),
            'temperature' => 0.1,
            'max_tokens' => 2048,
        ];

        $response = $this->request('/chat/completions', $payload);

        return $response['choices'][0]['message']['content'] ?? '';
    }

    /**
     * Chat with native tool calling support.
     *
     * OpenAI tool calling flow:
     * 1. Send request with `tools` array → LLM responds with `tool_calls` in message
     * 2. Send `tool` role messages back → LLM continues reasoning
     */
    public function chatWithTools(string $systemPrompt, array $messages, array $tools): LlmResponse
    {
        $openaiMessages = $this->convertMessages($messages);

        $payload = [
            'model'       => $this->model,
            'messages'    => array_merge(
                [['role' => 'system', 'content' => $systemPrompt]],
                $openaiMessages,
            ),
            'temperature' => 0.1,
            'max_tokens'  => 2048,
        ];

        // Only include tools when available (omit for generate stopping)
        if (!empty($tools)) {
            $payload['tools'] = $tools;
        }

        $response = $this->request('/chat/completions', $payload);

        return $this->parseLlmResponse($response);
    }

    /**
     * Convert normalized tool messages to OpenAI API format.
     *
     * Handles merging consecutive assistant tool_call messages into a single
     * message with multiple tool_calls (required for parallel tool calls).
     */
    private function convertMessages(array $messages): array
    {
        $result = [];

        foreach ($messages as $msg) {
            if (isset($msg['tool_call'])) {
                $toolCallEntry = [
                    'id'       => $msg['tool_call']['id'],
                    'type'     => 'function',
                    'function' => [
                        'name'      => $msg['tool_call']['name'],
                        'arguments' => json_encode($msg['tool_call']['params'], JSON_UNESCAPED_UNICODE),
                    ],
                ];

                // Merge with previous assistant message if consecutive
                $lastIdx = count($result) - 1;
                if ($lastIdx >= 0 && ($result[$lastIdx]['role'] ?? '') === 'assistant' && isset($result[$lastIdx]['tool_calls'])) {
                    $result[$lastIdx]['tool_calls'][] = $toolCallEntry;
                } else {
                    $result[] = [
                        'role'       => 'assistant',
                        'content'    => $msg['content'] ?? null,
                        'tool_calls' => [$toolCallEntry],
                    ];
                }

            } elseif (($msg['role'] ?? '') === 'tool_result') {
                // Tool result → OpenAI tool message (one per tool call — no merging needed)
                $result[] = [
                    'role'         => 'tool',
                    'tool_call_id' => $msg['tool_call_id'],
                    'content'      => $msg['content'],
                ];

            } else {
                // Standard message — pass through
                $result[] = $msg;
            }
        }

        return $result;
    }

    public function stream(string $systemPrompt, array $messages, callable $onToken): string
    {
        $payload = [
            'model' => $this->model,
            'messages' => array_merge(
                [['role' => 'system', 'content' => $systemPrompt]],
                $messages,
            ),
            'temperature' => 0.1,
            'max_tokens' => 2048,
            'stream' => true,
        ];

        return $this->requestStream('/chat/completions', $payload, $onToken);
    }

    /**
     * Make a standard (non-streaming) API request.
     */
    private function request(string $endpoint, array $payload): array
    {
        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL => rtrim($this->baseUrl, '/') . $endpoint,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                "Authorization: Bearer {$this->apiKey}",
            ],
            CURLOPT_TIMEOUT => 60,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new \RuntimeException("Botovis OpenAI request failed: {$error}");
        }

        $decoded = json_decode($response, true);

        if ($httpCode !== 200) {
            $errorMsg = $decoded['error']['message'] ?? "HTTP {$httpCode}";
            throw new \RuntimeException("Botovis OpenAI API error: {$errorMsg}");
        }

        return $decoded;
    }

    /**
     * Make a streaming API request, calling $onToken for each chunk.
     */
    private function requestStream(string $endpoint, array $payload, callable $onToken): string
    {
        $ch = curl_init();
        $fullResponse = '';

        curl_setopt_array($ch, [
            CURLOPT_URL => rtrim($this->baseUrl, '/') . $endpoint,
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                "Authorization: Bearer {$this->apiKey}",
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
                    if ($json === '[DONE]') {
                        break;
                    }

                    $parsed = json_decode($json, true);
                    $token = $parsed['choices'][0]['delta']['content'] ?? '';

                    if ($token !== '') {
                        $fullResponse .= $token;
                        $onToken($token);
                    }
                }
                return strlen($data);
            },
        ]);

        curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new \RuntimeException("Botovis OpenAI stream failed: {$error}");
        }

        return $fullResponse;
    }

    /**
     * Parse OpenAI response into structured LlmResponse.
     *
     * Supports parallel tool calls — when multiple tool_calls are returned,
     * creates an LlmResponse::parallelToolCalls.
     */
    private function parseLlmResponse(array $response): LlmResponse
    {
        $message = $response['choices'][0]['message'] ?? [];
        $toolCalls = $message['tool_calls'] ?? [];
        $content = $message['content'] ?? null;

        if (!empty($toolCalls)) {
            $calls = array_map(function ($call) {
                $params = json_decode($call['function']['arguments'] ?? '{}', true) ?? [];
                return [
                    'id'     => $call['id'],
                    'name'   => $call['function']['name'],
                    'params' => $params,
                ];
            }, $toolCalls);

            if (count($calls) > 1) {
                return LlmResponse::parallelToolCalls($calls, $content);
            }

            return LlmResponse::toolCall(
                toolName: $calls[0]['name'],
                toolParams: $calls[0]['params'],
                toolCallId: $calls[0]['id'],
                thought: $content,
            );
        }

        return LlmResponse::text($content ?? '');
    }
}
