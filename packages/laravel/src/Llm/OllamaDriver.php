<?php

declare(strict_types=1);

namespace Botovis\Laravel\Llm;

use Botovis\Core\Contracts\LlmDriverInterface;

/**
 * Ollama (local) LLM Driver.
 *
 * Ollama uses OpenAI-compatible API format, so this is mostly a wrapper.
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
}
