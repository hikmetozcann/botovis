<?php

declare(strict_types=1);

namespace Botovis\Telegram\Commands;

use Botovis\Telegram\TelegramApi;
use Illuminate\Console\Command;

/**
 * Set up the Telegram bot webhook and bot commands.
 *
 * Usage:
 *   php artisan botovis:telegram-setup              — Set webhook + bot menu
 *   php artisan botovis:telegram-setup --remove      — Remove webhook
 *   php artisan botovis:telegram-setup --info         — Show webhook status
 */
class SetupCommand extends Command
{
    protected $signature = 'botovis:telegram-setup
        {--remove : Remove the webhook}
        {--info : Show current webhook info}
        {--url= : Override webhook URL (auto-detected from APP_URL by default)}';

    protected $description = 'Set up Telegram bot webhook and commands for Botovis';

    public function handle(): int
    {
        $token = config('botovis-telegram.bot_token');

        if (empty($token)) {
            $this->error('BOTOVIS_TELEGRAM_BOT_TOKEN is not set in .env');
            return self::FAILURE;
        }

        $api = new TelegramApi($token);

        // --info: show current webhook status
        if ($this->option('info')) {
            return $this->showInfo($api);
        }

        // --remove: delete webhook
        if ($this->option('remove')) {
            return $this->removeWebhook($api);
        }

        // Default: set up webhook + commands
        return $this->setup($api);
    }

    /**
     * Set up webhook and bot commands.
     */
    private function setup(TelegramApi $api): int
    {
        // 1. Verify bot token
        $this->info('🔍 Verifying bot token...');
        $me = $api->getMe();

        if (!($me['ok'] ?? false)) {
            $this->error('Invalid bot token. Check BOTOVIS_TELEGRAM_BOT_TOKEN.');
            return self::FAILURE;
        }

        $botName = $me['result']['first_name'] ?? 'Bot';
        $botUsername = $me['result']['username'] ?? '';
        $this->line("   ✅ Bot: {$botName} (@{$botUsername})");

        // 2. Set webhook
        $prefix = config('botovis-telegram.route.prefix', 'botovis/telegram');
        $url = $this->option('url') ?? rtrim(config('app.url', ''), '/') . '/' . ltrim($prefix, '/') . '/webhook';
        $secret = config('botovis-telegram.webhook_secret');

        $this->info('🔗 Setting webhook...');
        $this->line("   URL: {$url}");

        $result = $api->setWebhook($url, $secret);

        if ($result['ok'] ?? false) {
            $this->line('   ✅ Webhook set successfully');
        } else {
            $desc = $result['description'] ?? 'Unknown error';
            $this->error("   Failed to set webhook: {$desc}");
            return self::FAILURE;
        }

        // 3. Register bot commands
        $this->info('📋 Registering bot commands...');

        $commands = [
            ['command' => 'start', 'description' => 'Botu başlat'],
            ['command' => 'connect', 'description' => 'Hesabınızı bağlayın — /connect KODUNUZ'],
            ['command' => 'disconnect', 'description' => 'Telegram bağlantısını kaldırın'],
            ['command' => 'tables', 'description' => 'Erişilebilir tabloları listeleyin'],
            ['command' => 'reset', 'description' => 'Konuşmayı sıfırlayın'],
            ['command' => 'status', 'description' => 'Bağlantı durumunu görün'],
            ['command' => 'help', 'description' => 'Yardım'],
        ];

        $api->setMyCommands($commands);
        $this->line('   ✅ Bot commands registered');

        // 4. Summary
        $this->line('');
        $this->info('🎉 Telegram bot is ready!');
        $this->line('');
        $this->line("   Bot: @{$botUsername}");
        $this->line("   Webhook: {$url}");
        $this->line("   Secret: " . ($secret ? 'configured' : 'not set (recommended)'));
        $this->line('');
        $this->line('   Next steps:');
        $this->line('   1. Make sure BOTOVIS_TELEGRAM_ENABLED=true in .env');
        $this->line("   2. Open https://t.me/{$botUsername} and send /start");
        $this->line('   3. Generate a connect code from your app panel');

        return self::SUCCESS;
    }

    /**
     * Remove webhook.
     */
    private function removeWebhook(TelegramApi $api): int
    {
        $this->info('🗑️  Removing webhook...');

        $result = $api->deleteWebhook();

        if ($result['ok'] ?? false) {
            $this->info('✅ Webhook removed.');
        } else {
            $this->error('Failed: ' . ($result['description'] ?? 'Unknown error'));
            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * Show current webhook info.
     */
    private function showInfo(TelegramApi $api): int
    {
        $me = $api->getMe();
        $info = $api->getWebhookInfo();

        $this->info('🤖 Bot Info:');
        if ($me['ok'] ?? false) {
            $this->line('   Name: ' . ($me['result']['first_name'] ?? 'N/A'));
            $this->line('   Username: @' . ($me['result']['username'] ?? 'N/A'));
        }

        $this->line('');
        $this->info('🔗 Webhook Info:');

        $webhook = $info['result'] ?? [];
        $this->line('   URL: ' . ($webhook['url'] ?: '(not set)'));
        $this->line('   Has secret: ' . ($webhook['has_custom_certificate'] ?? false ? 'yes' : 'no'));
        $this->line('   Pending updates: ' . ($webhook['pending_update_count'] ?? 0));

        if (!empty($webhook['last_error_message'])) {
            $this->warn('   Last error: ' . $webhook['last_error_message']);
            $this->line('   Error date: ' . date('Y-m-d H:i:s', $webhook['last_error_date'] ?? 0));
        }

        $this->line('');
        $this->info('⚙️  Config:');
        $this->line('   Enabled: ' . (config('botovis-telegram.enabled') ? 'yes' : 'no'));
        $this->line('   Show steps: ' . (config('botovis-telegram.show_steps') ? 'yes' : 'no'));

        return self::SUCCESS;
    }
}
