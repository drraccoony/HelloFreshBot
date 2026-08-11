<?php

declare(strict_types=1);

namespace App\Console\Commands;

use DefStudio\Telegraph\Models\TelegraphBot;
use Illuminate\Console\Command;

/**
 * Makes the bot + its chat allowlist reproducible from env vars instead of one-off
 * tinker edits — safe to run on every deploy (idempotent: adds missing chats, removes
 * ones no longer listed, leaves everything else untouched).
 */
class SyncTelegraphAllowlist extends Command
{
    protected $signature = 'telegraph:sync';

    protected $description = 'Sync the Telegraph bot and its allowed chats from TELEGRAM_BOT_TOKEN / TELEGRAM_ALLOWED_CHAT_IDS.';

    public function handle(): int
    {
        $token = config('services.telegram.bot_token');

        if (! $token) {
            $this->error('TELEGRAM_BOT_TOKEN is not set.');

            return self::FAILURE;
        }

        $bot = TelegraphBot::firstOrNew(['token' => $token]);

        if (! $bot->exists) {
            $bot->name = 'HelloFreshBot';
            $bot->save();
            $this->info('Created bot.');
        }

        $allowedIds = collect(explode(',', (string) config('services.telegram.allowed_chat_ids')))
            ->map(fn (string $id) => trim($id))
            ->filter()
            ->unique()
            ->values();

        if ($allowedIds->isEmpty()) {
            $this->warn('TELEGRAM_ALLOWED_CHAT_IDS is empty — no chat will be able to interact with the bot.');
        }

        $allowedIds->each(function (string $chatId) use ($bot) {
            $chat = $bot->chats()->firstOrNew(['chat_id' => $chatId]);

            if (! $chat->exists) {
                $chat->name = "Allowed chat {$chatId}";
                $chat->save();
                $this->info("Registered chat {$chatId}.");
            }
        });

        $bot->chats()->whereNotIn('chat_id', $allowedIds)->get()->each(function ($chat) {
            $this->info("Removing chat {$chat->chat_id} (no longer in TELEGRAM_ALLOWED_CHAT_IDS).");
            $chat->delete();
        });

        $this->info("Synced — {$allowedIds->count()} chat(s) allowed.");

        return self::SUCCESS;
    }
}
