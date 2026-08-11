<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\HelloFresh\WeeklyDigestSender;
use DefStudio\Telegraph\Models\TelegraphChat;
use Illuminate\Console\Command;

class SendHelloFreshWeeklyDigest extends Command
{
    protected $signature = 'hellofresh:weekly-digest';

    protected $description = "Message the Telegram chat with next week's HelloFresh meals, and a free add-on picker if one isn't set yet.";

    public function handle(WeeklyDigestSender $sender): int
    {
        $chat = TelegraphChat::first();

        if ($chat === null) {
            $this->error('No Telegraph chat is registered yet. Run `php artisan telegraph:new-bot` first.');

            return self::FAILURE;
        }

        return $sender->sendTo($chat) ? self::SUCCESS : self::FAILURE;
    }
}
