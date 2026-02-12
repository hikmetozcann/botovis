<?php

declare(strict_types=1);

namespace Botovis\Laravel\Commands;

use Illuminate\Console\Command;
use Botovis\Core\Contracts\LlmDriverInterface;
use Botovis\Core\Contracts\SchemaDiscoveryInterface;
use Botovis\Core\Intent\IntentResolver;
use Botovis\Core\Intent\ResolvedIntent;
use Botovis\Core\Enums\IntentType;

/**
 * Interactive terminal chat for testing Botovis intent resolution.
 *
 * Usage: php artisan botovis:chat
 *
 * This is a developer testing tool. It lets you type natural language
 * messages and see how Botovis interprets them.
 */
class ChatCommand extends Command
{
    protected $signature = 'botovis:chat';
    protected $description = 'Interactive chat to test Botovis intent resolution (developer tool)';

    public function handle(
        SchemaDiscoveryInterface $discovery,
        LlmDriverInterface $llm,
    ): int {
        $schema = $discovery->discover();

        if (count($schema->tables) === 0) {
            $this->error('No models configured. Run `php artisan botovis:discover` first.');
            return self::FAILURE;
        }

        $resolver = new IntentResolver($llm, $schema);

        $this->info('🤖 Botovis Chat (type "exit" to quit)');
        $this->line("   Driver: {$llm->name()}");
        $this->line("   Models: " . implode(', ', $schema->getTableNames()));
        $this->line('');

        $history = [];

        while (true) {
            $input = $this->ask('Sen');

            if ($input === null || strtolower(trim($input)) === 'exit') {
                $this->info('👋 Görüşürüz!');
                break;
            }

            if (trim($input) === '') {
                continue;
            }

            try {
                $this->line('');
                $this->line('<fg=gray>Düşünüyorum...</>');

                $intent = $resolver->resolve($input, $history);

                // Add to history
                $history[] = ['role' => 'user', 'content' => $input];
                $history[] = ['role' => 'assistant', 'content' => json_encode($intent->toArray())];

                // Display result
                $this->displayIntent($intent);

            } catch (\Throwable $e) {
                $this->error("Hata: {$e->getMessage()}");
            }

            $this->line('');
        }

        return self::SUCCESS;
    }

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

        if ($intent->requiresConfirmation()) {
            $this->line('');
            $this->warn("⚠️  Bu işlem onay gerektirir (create/update/delete)");
            $this->line("   " . $intent->toConfirmationMessage());
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
}
