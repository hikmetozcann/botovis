<?php

declare(strict_types=1);

namespace Botovis\Laravel\Llm;

use Botovis\Core\Contracts\LlmDriverInterface;

/**
 * OpenAI LLM Driver.
 *
 * Communicates with OpenAI API (or any OpenAI-compatible endpoint).
 * Uses plain cURL — no external HTTP client dependency needed.
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
}
