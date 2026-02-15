<?php

declare(strict_types=1);

namespace Botovis\Laravel\Llm;

use Botovis\Core\Contracts\LlmDriverInterface;
use Botovis\Core\DTO\LlmResponse;

/**
 * Ollama (local) LLM Driver.
 *
 * Ollama uses OpenAI-compatible API format, including tool calling.
 */
class OllamaDriver implements LlmDriverInterface
{
    public function __construct(
        private readonly string $model = 'llama3',
        private readonly string $baseUrl = 'http://localhost:11434',
    ) {}

    public function name(): string
    {
        return 'ollama';
    }

    public function chat(string $systemPrompt, array $messages): string
    {
        $payload = [
            'model' => $this->model,
            'messages' => array_merge(
                [['role' => 'system', 'content' => $systemPrompt]],
                $messages,
            ),
            'stream' => false,
        ];

        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL => rtrim($this->baseUrl, '/') . '/api/chat',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT => 120,
        ]);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new \RuntimeException("Botovis Ollama request failed: {$error}");
        }

        $decoded = json_decode($response, true);

        return $decoded['message']['content'] ?? '';
    }

    /**
     * Chat with native tool calling support (OpenAI-compatible format).
     *
     * Note: Requires Ollama 0.3+ and a model that supports tool calling
     * (e.g., llama3.1, mistral-nemo, qwen2.5, etc.)
     */
    public function chatWithTools(string $systemPrompt, array $messages, array $tools): LlmResponse
    {
        $ollamaMessages = $this->convertMessages($messages);

        $payload = [
            'model'    => $this->model,
            'messages' => array_merge(
                [['role' => 'system', 'content' => $systemPrompt]],
                $ollamaMessages,
            ),
            'tools'  => $tools,
            'stream' => false,
        ];

        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL => rtrim($this->baseUrl, '/') . '/api/chat',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT => 120,
        ]);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new \RuntimeException("Botovis Ollama request failed: {$error}");
        }

        $decoded = json_decode($response, true);

        return $this->parseLlmResponse($decoded);
    }

    public function stream(string $systemPrompt, array $messages, callable $onToken): string
    {
        $payload = [
            'model' => $this->model,
            'messages' => array_merge(
                [['role' => 'system', 'content' => $systemPrompt]],
                $messages,
            ),
            'stream' => true,
        ];

        $ch = curl_init();
        $fullResponse = '';

        curl_setopt_array($ch, [
            CURLOPT_URL => rtrim($this->baseUrl, '/') . '/api/chat',
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT => 120,
            CURLOPT_WRITEFUNCTION => function ($ch, $data) use ($onToken, &$fullResponse) {
                $parsed = json_decode(trim($data), true);
                $token = $parsed['message']['content'] ?? '';

                if ($token !== '') {
                    $fullResponse .= $token;
                    $onToken($token);
                }

                return strlen($data);
            },
        ]);

        curl_exec($ch);
        curl_close($ch);

        return $fullResponse;
    }

    /**
     * Convert normalized tool messages to OpenAI-compatible format (used by Ollama).
     */
    private function convertMessages(array $messages): array
    {
        $result = [];

        foreach ($messages as $msg) {
            if (isset($msg['tool_call'])) {
                $result[] = [
                    'role'       => 'assistant',
                    'content'    => $msg['content'] ?? '',
                    'tool_calls' => [
                        [
                            'id'       => $msg['tool_call']['id'],
                            'type'     => 'function',
                            'function' => [
                                'name'      => $msg['tool_call']['name'],
                                'arguments' => json_encode($msg['tool_call']['params'], JSON_UNESCAPED_UNICODE),
                            ],
                        ],
                    ],
                ];
            } elseif (($msg['role'] ?? '') === 'tool_result') {
                $result[] = [
                    'role'         => 'tool',
                    'tool_call_id' => $msg['tool_call_id'],
                    'content'      => $msg['content'],
                ];
            } else {
                $result[] = $msg;
            }
        }

        return $result;
    }

    /**
     * Parse Ollama response (OpenAI-compatible format).
     */
    private function parseLlmResponse(array $response): LlmResponse
    {
        $message = $response['message'] ?? [];
        $toolCalls = $message['tool_calls'] ?? [];
        $content = $message['content'] ?? null;

        if (!empty($toolCalls)) {
            $call = $toolCalls[0];
            $fn = $call['function'] ?? [];
            $params = $fn['arguments'] ?? [];

            // Ollama returns arguments as array (not string like OpenAI)
            if (is_string($params)) {
                $params = json_decode($params, true) ?? [];
            }

            return LlmResponse::toolCall(
                toolName: $fn['name'] ?? '',
                toolParams: $params,
                toolCallId: $call['id'] ?? ('ollama_' . uniqid()),
                thought: $content,
            );
        }

        return LlmResponse::text($content ?? '');
    }
}
