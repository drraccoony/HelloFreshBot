<?php

declare(strict_types=1);

namespace App\Telegram;

use App\HelloFresh\WeeklyDigestSender;
use DefStudio\Telegraph\Handlers\WebhookHandler;
use DefStudio\Telegraph\Keyboard\Keyboard;
use Illuminate\Support\Stringable;
use RicoFresh\HelloFreshApi\Exceptions\HelloFreshException;
use RicoFresh\HelloFreshApi\HelloFreshClient;

class HelloFreshWebhookHandler extends WebhookHandler
{
    public function start(): void
    {
        $this->chat->html("👋 Hi! I'll message you every Monday with next week's HelloFresh meals, and let you pick a free add-on if you haven't set one yet. Send /remindme any time for an on-demand check, or /remindme 2 through /remindme 5 to look further ahead.")->send();
    }

    /**
     * /remindme with no argument = next week; /remindme 2 = the week after that, up to
     * /remindme 5 (clamped in WeeklyDigestSender).
     */
    public function remindme(string $parameter = ''): void
    {
        app(WeeklyDigestSender::class)->sendTo($this->chat, (int) trim($parameter) ?: 1);
    }

    protected function handleChatMessage(Stringable $text): void
    {
        $this->chat->html('I only send the weekly HelloFresh digest and react to its buttons — try /remindme.')->send();
    }

    /**
     * Bound to the inline keyboard buttons on the weekly digest message
     * (Button::action('setAddon')->param('week', ...)->param('index', ...)).
     */
    public function setAddon(string $week, string $index): void
    {
        $client = app(HelloFreshClient::class);

        try {
            $addon = collect($client->getFreeAddonsForWeek($week))
                ->first(fn (array $option) => (string) $option['index'] === $index);

            if ($addon === null) {
                $this->reply('That add-on is no longer available — check the HelloFresh app.', true);

                return;
            }

            $client->setFreeAddon($week, $addon);
        } catch (HelloFreshException $e) {
            report($e);
            $this->reply('Could not reach HelloFresh to set that add-on. Check the bot logs.', true);

            return;
        }

        $this->reply("Set: {$addon['name']}");

        $this->chat->edit($this->messageId)
            ->html("✅ Free add-on for <b>{$week}</b>: <b>".e($addon['name']).'</b>')
            ->keyboard(Keyboard::make())
            ->send();
    }
}
