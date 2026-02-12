<?php

declare(strict_types=1);

namespace Botovis\Laravel\Commands;

use Illuminate\Console\Command;
use Botovis\Core\Orchestrator;
use Botovis\Core\OrchestratorResponse;
use Botovis\Core\Contracts\LlmDriverInterface;
use Botovis\Core\Contracts\SchemaDiscoveryInterface;
use Botovis\Core\Contracts\ActionResult;
use Botovis\Core\Intent\ResolvedIntent;
use Botovis\Core\Enums\IntentType;

/**
 * Interactive terminal chat for testing Botovis — full flow.
 *
 * Usage: php artisan botovis:chat
 *
 * This is a developer testing tool. It uses the same Orchestrator
 * that powers the HTTP API, but renders output in the terminal.
 */
class ChatCommand extends Command
{
    protected $signature = 'botovis:chat';
    protected $description = 'Interactive chat to test Botovis (developer tool)';

    private bool $pendingConfirmation = false;

    public function handle(
        Orchestrator $orchestrator,
        SchemaDiscoveryInterface $discovery,
        LlmDriverInterface $llm,
    ): int {
        $schema = $discovery->discover();

        if (count($schema->tables) === 0) {
            $this->error('No models configured. Run `php artisan botovis:discover` first.');
            return self::FAILURE;
        }

        $conversationId = 'cli_' . uniqid();

        $this->info('🤖 Botovis Chat (type "exit" to quit)');
        $this->line("   Driver: {$llm->name()}");
        $this->line("   Models: " . implode(', ', $schema->getTableNames()));
        $this->line('');

        while (true) {
            $input = $this->ask('Sen');

            if ($input === null || strtolower(trim($input)) === 'exit') {
                $this->info('👋 Görüşürüz!');
                break;
            }

            if (trim($input) === '') {
                continue;
            }

            $this->line('');

            try {
                $this->line('<fg=gray>Düşünüyorum...</>');
                $response = $orchestrator->handle($conversationId, $input);
                $this->renderResponse($response);
            } catch (\Throwable $e) {
                $this->error("Hata: {$e->getMessage()}");
            }

            $this->line('');
        }

        return self::SUCCESS;
    }

    // ──────────────────────────────────────────────
    //  Response Rendering
    // ──────────────────────────────────────────────

    private function renderResponse(OrchestratorResponse $response): void
    {
        // Show intermediate auto-continue steps
        foreach ($response->steps as $step) {
            $this->displayIntent($step['intent']);
            $this->line('');
            $this->line('<fg=gray>Sorgu çalıştırılıyor...</>');
            $this->displayReadResult($step['result']);
            $this->line('');
            $this->line('<fg=gray>Sonraki adıma geçiyorum...</>');
        }

        match ($response->type) {
            'message' => $this->renderMessage($response),
            'confirmation' => $this->renderConfirmation($response),
            'executed' => $this->renderExecuted($response),
            'rejected' => $this->renderRejected(),
            'error' => $this->error("❌ {$response->message}"),
            default => $this->line($response->message),
        };
    }

    private function renderMessage(OrchestratorResponse $response): void
    {
        if ($response->intent !== null) {
            $this->displayIntent($response->intent);
        } else {
            $this->line('');
            $this->info("💬 Cevap:");
            $this->line("   {$response->message}");
        }
    }

    private function renderConfirmation(OrchestratorResponse $response): void
    {
        if ($response->intent !== null) {
            $this->displayIntent($response->intent);
        }
        $this->line('');
        $this->warn('⚠️  Bu işlemi onaylıyor musunuz? (evet/hayır)');
    }

    private function renderExecuted(OrchestratorResponse $response): void
    {
        if ($response->intent !== null) {
            $this->displayIntent($response->intent);
        }

        if ($response->result === null) {
            return;
        }

        $this->line('');

        // For write operations, show compact result
        if ($response->intent?->requiresConfirmation()) {
            $this->line('<fg=gray>İşlem yürütülüyor...</>');
            $this->displayWriteResult($response->result, $response->intent);
        } else {
            $this->line('<fg=gray>Sorgu çalıştırılıyor...</>');
            $this->displayReadResult($response->result);
        }
    }

    private function renderRejected(): void
    {
        $this->info('❌ İşlem iptal edildi.');
    }

    // ──────────────────────────────────────────────
    //  Display Helpers
    // ──────────────────────────────────────────────

    private function displayIntent(ResolvedIntent $intent): void
    {
        match ($intent->type) {
            IntentType::ACTION => $this->displayAction($intent),
            IntentType::QUESTION => $this->displayTextResponse('💬 Cevap:', $intent->message),
            IntentType::CLARIFICATION => $this->displayTextResponse('❓ Bilgi Gerekli:', $intent->message, 'warn'),
            IntentType::UNKNOWN => $this->displayTextResponse('❌ Anlaşılamadı:', $intent->message, 'error'),
        };
    }

    private function displayAction(ResolvedIntent $intent): void
    {
        $this->line('');
        $this->info("📌 Aksiyon Tespit Edildi");
        $this->line("   Tablo:  <fg=cyan>{$intent->table}</>");
        $this->line("   İşlem:  <fg=yellow>{$intent->action->value}</>");
        $this->line("   Güven:  {$intent->confidence}");

        if (!empty($intent->data)) {
            $this->line("   Veri:");
            foreach ($intent->data as $key => $value) {
                $display = is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : $value;
                $this->line("     <fg=green>{$key}</>: {$display}");
            }
        }

        if (!empty($intent->where)) {
            $this->line("   Koşul:");
            foreach ($intent->where as $key => $value) {
                $val = is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE)
                     : (is_bool($value) ? ($value ? 'true' : 'false') : $value);
                $this->line("     <fg=magenta>{$key}</> = {$val}");
            }
        }

        if (!empty($intent->select)) {
            $this->line("   Sütunlar: <fg=blue>" . implode(', ', $intent->select) . "</>");
        }

        if ($intent->message) {
            $this->line("   Mesaj:  {$intent->message}");
        }
    }

    private function displayTextResponse(string $label, string $message, string $style = 'info'): void
    {
        $this->line('');
        $this->{$style}($label);
        $this->line("   {$message}");
    }

    private function displayReadResult(ActionResult $result): void
    {
        $this->line('');
        if ($result->success) {
            $this->info("✅ {$result->message}");
            $this->renderDataTable($result->data);
        } else {
            $this->error("❌ {$result->message}");
        }
    }

    private function displayWriteResult(ActionResult $result, ResolvedIntent $intent): void
    {
        $this->line('');

        if (!$result->success) {
            $this->error("❌ {$result->message}");
            return;
        }

        $this->info("✅ {$result->message}");

        if (empty($result->data)) {
            return;
        }

        $importantKeys = array_unique(array_merge(
            ['id'],
            array_keys($intent->data),
            array_keys($intent->where),
        ));

        $data = $result->data;

        if (isset($data[0]) && is_array($data[0])) {
            $filtered = array_map(fn ($row) => array_intersect_key($row, array_flip($importantKeys)), $data);
            $this->renderDataTable($filtered);
        } else {
            $filtered = array_intersect_key($data, array_flip($importantKeys));
            foreach ($filtered as $key => $value) {
                if (is_array($value)) $value = json_encode($value, JSON_UNESCAPED_UNICODE);
                $this->line("   <fg=green>{$key}</>: {$value}");
            }
        }
    }

    private function renderDataTable(array $data): void
    {
        if (empty($data)) {
            return;
        }

        if (count($data) > 10) {
            $data = array_slice($data, 0, 10);
            $this->line("<fg=gray>   (ilk 10 kayıt gösteriliyor)</>");
        }

        if (isset($data[0]) && is_array($data[0])) {
            $headers = array_keys($data[0]);

            $rows = array_map(function ($row) {
                return array_map(function ($val) {
                    if (is_array($val)) return json_encode($val, JSON_UNESCAPED_UNICODE);
                    $str = (string) $val;
                    return mb_strlen($str) > 40 ? mb_substr($str, 0, 40) . '...' : $str;
                }, $row);
            }, $data);

            $this->table($headers, $rows);
        } else {
            foreach ($data as $key => $value) {
                if (is_array($value)) $value = json_encode($value, JSON_UNESCAPED_UNICODE);
                $this->line("   <fg=green>{$key}</>: {$value}");
            }
        }
    }
}
