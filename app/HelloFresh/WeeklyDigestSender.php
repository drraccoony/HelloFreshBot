<?php

declare(strict_types=1);

namespace App\HelloFresh;

use DefStudio\Telegraph\Keyboard\Button;
use DefStudio\Telegraph\Keyboard\Keyboard;
use DefStudio\Telegraph\Models\TelegraphChat;
use RicoFresh\HelloFreshApi\Exceptions\HelloFreshException;
use RicoFresh\HelloFreshApi\HelloFreshClient;

/**
 * Builds and sends the "upcoming meals + free add-on status" message — shared by the
 * scheduled hellofresh:weekly-digest command and the /remindme chat command.
 */
class WeeklyDigestSender
{
    private const MAX_WEEKS_OUT = 5;

    public function __construct(private readonly HelloFreshClient $client) {}

    /**
     * @param  int  $weeksOut  1 = next week (the default), 2 = the week after that, ...
     *                         up to self::MAX_WEEKS_OUT.
     */
    public function sendTo(TelegraphChat $chat, int $weeksOut = 1): bool
    {
        $weeksOut = max(1, min(self::MAX_WEEKS_OUT, $weeksOut));

        try {
            $week = $this->deliveryWeek($weeksOut);
        } catch (HelloFreshException $e) {
            report($e);
            $chat->html("⚠️ Couldn't reach HelloFresh to build this digest. Check the bot logs.")->send();

            return false;
        }

        if ($week === null) {
            $chat->html("No HelloFresh delivery found {$weeksOut} week(s) out.")->send();

            return true;
        }

        try {
            $meals = $this->client->getCurrentMeals($week);
            $currentAddon = $this->client->getCurrentFreeAddon($week);
            $addonOptions = $currentAddon === null
                ? collect($this->client->getFreeAddonsForWeek($week))->reject(fn (array $option) => $option['isSoldOut'] ?? false)
                : collect();
        } catch (HelloFreshException $e) {
            report($e);
            $message = str_contains($e->getMessage(), 'HTTP 404')
                ? "That week's menu isn't planned yet — try a smaller number."
                : "⚠️ Couldn't reach HelloFresh to build this digest. Check the bot logs.";
            $chat->html($message)->send();

            return false;
        }

        $weekLabel = $weeksOut === 1 ? "next week's box" : "the box {$weeksOut} weeks out";
        $lines = ['🍽 <b>'.ucfirst($weekLabel)." ({$week})</b>"];
        $lines[] = $meals === []
            ? '— no meals selected yet —'
            : collect($meals)->map(fn (array $meal) => '• '.e($meal['name']))->implode("\n");

        $keyboard = Keyboard::make();
        $lines[] = '';

        if ($currentAddon !== null) {
            $lines[] = '✅ Free add-on already set: <b>'.e($currentAddon['name']).'</b>';
        } elseif ($addonOptions->isEmpty()) {
            $lines[] = '⚠️ No free add-on set, and no options are available yet.';
        } else {
            $lines[] = '⚠️ <b>No free add-on selected yet</b> — pick one:';

            $keyboard = $keyboard->buttons(
                $addonOptions->map(fn (array $option) => Button::make($option['name'])
                    ->action('setAddon')
                    ->param('week', $week)
                    ->param('index', (string) $option['index']))->all()
            )->chunk(1);
        }

        $chat->html(implode("\n", $lines))->keyboard($keyboard)->send();

        return true;
    }

    /**
     * The $weeksOut'th upcoming, not-yet-delivered ISO week key (e.g. "2026-W34") after
     * the current week — 1 is "next week's box", 2 is the week after that, etc. — skipping
     * any week already in progress or delivered.
     */
    private function deliveryWeek(int $weeksOut): ?string
    {
        $currentWeek = date('o-\WW');

        $delivery = collect($this->client->getUpcomingDeliveries()['items'] ?? [])
            ->filter(fn (array $delivery) => ($delivery['id'] ?? null) > $currentWeek
                && ($delivery['status'] ?? null) !== 'DELIVERED')
            ->sortBy('id')
            ->values()
            ->get($weeksOut - 1);

        return $delivery['id'] ?? null;
    }
}
