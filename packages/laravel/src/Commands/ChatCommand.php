<?php

declare(strict_types=1);

namespace Botovis\Laravel\Commands;

use Illuminate\Console\Command;
use Botovis\Core\Contracts\LlmDriverInterface;
use Botovis\Core\Contracts\SchemaDiscoveryInterface;
use Botovis\Core\Contracts\ActionExecutorInterface;
use Botovis\Core\Contracts\ActionResult;
use Botovis\Core\Intent\IntentResolver;
use Botovis\Core\Intent\ResolvedIntent;
use Botovis\Core\Conversation\ConversationState;
use Botovis\Core\Enums\IntentType;
use Botovis\Core\Enums\ActionType;

/**
 * Interactive terminal chat for testing Botovis — full flow.
 *
 * Usage: php artisan botovis:chat
 *
 * Flow:
 *   1. User types a message
 *   2. LLM resolves intent (CREATE/READ/UPDATE/DELETE/QUESTION)
 *   3. READ → execute immediately
 *   4. CREATE/UPDATE/DELETE → ask for confirmation
 *   5. User says "evet/onaylıyorum" → execute
 *   6. User says "hayır/iptal" → cancel
 */
class ChatCommand extends Command
{
    protected $signature = 'botovis:chat';
    protected $description = 'Interactive chat to test Botovis (developer tool)';

    public function handle(
        SchemaDiscoveryInterface $discovery,
        LlmDriverInterface $llm,
        ActionExecutorInterface $executor,
    ): int {
        $schema = $discovery->discover();

        if (count($schema->tables) === 0) {
            $this->error('No models configured. Run `php artisan botovis:discover` first.');
            return self::FAILURE;
        }

        $resolver = new IntentResolver($llm, $schema);
        $conversation = new ConversationState();

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

            // ── Check if user is responding to a pending confirmation ──
            if ($conversation->hasPendingIntent()) {
                $pending = $conversation->getPendingIntent();

                if (ConversationState::isConfirmation($input)) {
                    $this->line('<fg=gray>İşlem yürütülüyor...</>');
                    $result = $executor->execute(
                        $pending->table,
                        $pending->action,
                        $pending->data,
                        $pending->where,
                        $pending->select,
                    );
                    $conversation->clearPendingIntent();
                    $this->displayWriteResult($result, $pending);
                    $conversation->addUserMessage($input);
                    $conversation->addAssistantMessage($this->buildResultSummary($result));
                    $this->line('');
                    continue;
                }

                if (ConversationState::isRejection($input)) {
                    $conversation->clearPendingIntent();
                    $this->info('❌ İşlem iptal edildi.');
                    $conversation->addUserMessage($input);
                    $conversation->addAssistantMessage('İşlem iptal edildi.');

                    // Check if user added a message after rejection ("hayır ama ...")
                    $remainder = ConversationState::extractAfterRejection($input);
                    if ($remainder !== null) {
                        $this->line('');
                        $this->line('<fg=gray>Mesajınızı değerlendiriyorum...</>');
                        $input = $remainder;
                        // Fall through to intent resolution below
                    } else {
                        $this->line('');
                        continue;
                    }
                }

                // Not a confirmation/rejection → treat as a new message, clear pending
                $conversation->clearPendingIntent();
            }

            // ── Resolve intent via LLM ──
            try {
                $intent = $this->resolveAndExecute($input, $resolver, $executor, $conversation);
            } catch (\Throwable $e) {
                $this->error("Hata: {$e->getMessage()}");
            }

            $this->line('');
        }

        return self::SUCCESS;
    }

    /**
     * Core resolve → execute loop with auto-continue support.
     *
     * When a READ has autoContinue=true, the result is fed back to the LLM
     * and it automatically proceeds with the next step (max 3 auto-steps).
     */
    private function resolveAndExecute(
        string $input,
        IntentResolver $resolver,
        ActionExecutorInterface $executor,
        ConversationState $conversation,
        int $depth = 0,
    ): ?ResolvedIntent {
        $maxAutoSteps = 3;

        $this->line('<fg=gray>Düşünüyorum...</>');

        $intent = $resolver->resolve($input, $conversation->getHistory());

        $conversation->addUserMessage($input);
        $conversation->addAssistantMessage(json_encode($intent->toArray()));

        // Display intent info
        $this->displayIntent($intent);

        // ── Execute or ask for confirmation ──
        if ($intent->isAction()) {
            if ($intent->requiresConfirmation()) {
                // Store as pending → wait for user confirmation
                $conversation->setPendingIntent($intent);
                $this->line('');
                $this->warn('⚠️  Bu işlemi onaylıyor musunuz? (evet/hayır)');
            } else {
                // READ → execute immediately
                $this->line('');
                $this->line('<fg=gray>Sorgu çalıştırılıyor...</>');
                $result = $executor->execute(
                    $intent->table,
                    $intent->action,
                    $intent->data,
                    $intent->where,
                    $intent->select,
                );
                $this->displayResult($result);

                $resultSummary = $this->buildResultSummary($result);
                $conversation->addAssistantMessage($resultSummary);

                // ── Auto-continue: if this READ was a prerequisite, proceed ──
                if ($intent->autoContinue && $result->success && $depth < $maxAutoSteps) {
                    $this->line('');
                    $this->line('<fg=gray>Sonraki adıma geçiyorum...</>');
                    return $this->resolveAndExecute(
                        'Sonuçları gördüm, şimdi kullanıcının istediği bir sonraki işleme devam et.',
                        $resolver,
                        $executor,
                        $conversation,
                        $depth + 1,
                    );
                }
            }
        }

        return $intent;
    }

    // ──────────────────────────────────────────────
    //  Display Helpers
    // ──────────────────────────────────────────────

    private function displayIntent(ResolvedIntent $intent): void
    {
        match ($intent->type) {
            IntentType::ACTION => $this->displayAction($intent),
            IntentType::QUESTION => $this->displayQuestion($intent),
            IntentType::CLARIFICATION => $this->displayClarification($intent),
            IntentType::UNKNOWN => $this->displayUnknown($intent),
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

    private function displayQuestion(ResolvedIntent $intent): void
    {
        $this->line('');
        $this->info("💬 Cevap:");
        $this->line("   {$intent->message}");
    }

    private function displayClarification(ResolvedIntent $intent): void
    {
        $this->line('');
        $this->warn("❓ Bilgi Gerekli:");
        $this->line("   {$intent->message}");
    }

    private function displayUnknown(ResolvedIntent $intent): void
    {
        $this->line('');
        $this->error("❌ Anlaşılamadı:");
        $this->line("   {$intent->message}");
    }

    /**
     * Display READ result — full table view.
     */
    private function displayResult(ActionResult $result): void
    {
        $this->line('');

        if ($result->success) {
            $this->info("✅ {$result->message}");
            $this->renderDataTable($result->data);
        } else {
            $this->error("❌ {$result->message}");
        }
    }

    /**
     * Display write (CREATE/UPDATE/DELETE) result — compact summary.
     * Shows only the fields that changed + key identifiers.
     */
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

        // Build a compact set of columns to show
        $importantKeys = array_unique(array_merge(
            ['id'],
            array_keys($intent->data),     // changed fields
            array_keys($intent->where),    // identifier fields
        ));

        $data = $result->data;

        // Handle both single-record and multi-record results
        if (isset($data[0]) && is_array($data[0])) {
            // Filter each record to important keys only
            $filtered = array_map(function ($row) use ($importantKeys) {
                return array_intersect_key($row, array_flip($importantKeys));
            }, $data);
            $this->renderDataTable($filtered);
        } else {
            $filtered = array_intersect_key($data, array_flip($importantKeys));
            foreach ($filtered as $key => $value) {
                if (is_array($value)) $value = json_encode($value, JSON_UNESCAPED_UNICODE);
                $this->line("   <fg=green>{$key}</>: {$value}");
            }
        }
    }

    /**
     * Render an array of records as a table.
     */
    private function renderDataTable(array $data): void
    {
        if (empty($data)) {
            return;
        }

        // Limit display to first 10 records
        if (count($data) > 10) {
            $data = array_slice($data, 0, 10);
            $this->line("<fg=gray>   (ilk 10 kayıt gösteriliyor)</>");
        }

        // For flat arrays (list of records), render as table
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
            // Single record — key: value format
            foreach ($data as $key => $value) {
                if (is_array($value)) $value = json_encode($value, JSON_UNESCAPED_UNICODE);
                $this->line("   <fg=green>{$key}</>: {$value}");
            }
        }
    }

    /**
     * Build a concise text summary of execution result for LLM history.
     * Includes actual data so the LLM can reference it in future turns.
     */
    private function buildResultSummary(ActionResult $result): string
    {
        $summary = $result->success ? "[İşlem başarılı] " : "[İşlem başarısız] ";
        $summary .= $result->message;

        if (!empty($result->data)) {
            // Add data in a compact JSON for the LLM to reference
            $compactData = $result->data;
            // Limit to first 5 records to keep context window reasonable
            if (count($compactData) > 5) {
                $compactData = array_slice($compactData, 0, 5);
                $summary .= "\nSonuç (ilk 5): " . json_encode($compactData, JSON_UNESCAPED_UNICODE);
            } else {
                $summary .= "\nSonuç: " . json_encode($compactData, JSON_UNESCAPED_UNICODE);
            }
        }

        return $summary;
    }
}
