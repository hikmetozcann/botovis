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
                    );
                    $conversation->clearPendingIntent();
                    $this->displayResult($result);
                    $conversation->addUserMessage($input);
                    $conversation->addAssistantMessage($result->message);
                    $this->line('');
                    continue;
                }

                if (ConversationState::isRejection($input)) {
                    $conversation->clearPendingIntent();
                    $this->info('❌ İşlem iptal edildi.');
                    $conversation->addUserMessage($input);
                    $conversation->addAssistantMessage('İşlem iptal edildi.');
                    $this->line('');
                    continue;
                }

                // Not a confirmation/rejection → treat as a new message, clear pending
                $conversation->clearPendingIntent();
            }

            // ── Resolve intent via LLM ──
            try {
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
                        );
                        $this->displayResult($result);
                        $conversation->addAssistantMessage($result->message);
                    }
                }

            } catch (\Throwable $e) {
                $this->error("Hata: {$e->getMessage()}");
            }

            $this->line('');
        }

        return self::SUCCESS;
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
                $this->line("     <fg=green>{$key}</>: {$value}");
            }
        }

        if (!empty($intent->where)) {
            $this->line("   Koşul:");
            foreach ($intent->where as $key => $value) {
                $val = is_bool($value) ? ($value ? 'true' : 'false') : $value;
                $this->line("     <fg=magenta>{$key}</> = {$val}");
            }
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

    private function displayResult(ActionResult $result): void
    {
        $this->line('');

        if ($result->success) {
            $this->info("✅ {$result->message}");

            if (!empty($result->data)) {
                // Show results in a table for READ operations
                $data = $result->data;

                // Limit display to first 10 records
                if (count($data) > 10) {
                    $data = array_slice($data, 0, 10);
                    $this->line("<fg=gray>   (ilk 10 kayıt gösteriliyor)</>");
                }

                // For flat arrays (list of records), render as table
                if (isset($data[0]) && is_array($data[0])) {
                    $headers = array_keys($data[0]);

                    // Truncate long values for display
                    $rows = array_map(function ($row) {
                        return array_map(function ($val) {
                            if (is_array($val)) return json_encode($val);
                            $str = (string) $val;
                            return mb_strlen($str) > 40 ? mb_substr($str, 0, 40) . '...' : $str;
                        }, $row);
                    }, $data);

                    $this->table($headers, $rows);
                } else {
                    // Single record — key: value format
                    foreach ($data as $key => $value) {
                        if (is_array($value)) {
                            $value = json_encode($value);
                        }
                        $this->line("   <fg=green>{$key}</>: {$value}");
                    }
                }
            }
        } else {
            $this->error("❌ {$result->message}");
        }
    }
}
