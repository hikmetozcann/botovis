<?php

declare(strict_types=1);

namespace Botovis\Laravel\Llm;

use Botovis\Core\Contracts\LlmDriverInterface;

/**
 * Anthropic (Claude) LLM Driver.
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
