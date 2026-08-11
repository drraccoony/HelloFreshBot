<?php

declare(strict_types=1);

namespace App\Console\Commands;

use DefStudio\Telegraph\Models\TelegraphChat;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use RicoFresh\HelloFreshApi\Exceptions\HelloFreshException;
use RicoFresh\HelloFreshApi\HelloFreshClient;

class KeepHelloFreshTokenAlive extends Command
{
    private const BROKEN_FLAG_CACHE_KEY = 'hellofresh:auth-broken';

    protected $signature = 'hellofresh:keep-alive';

    protected $description = "Ping HelloFresh with a lightweight request so HelloFreshClient's "
        .'auto-refresh keeps the session token from ever going stale, and alert on Telegram if auth breaks.';

    public function handle(HelloFreshClient $client): int
    {
        try {
            // Cheapest confirmed-working authenticated endpoint — just needs
            // ensureAuthenticated() to run, which refreshes the token if it's close to expiry.
            $client->getSubscriptions();
        } catch (HelloFreshException $e) {
            report($e);
            $this->error('HelloFresh keep-alive failed: '.$e->getMessage());
            $this->notifyBroken();

            return self::FAILURE;
        }

        $this->info('HelloFresh token is alive.');
        $this->notifyRecoveredIfNeeded();

        return self::SUCCESS;
    }

    /**
     * Alert once when auth first breaks, not on every failed ping — 15-minute polling
     * would otherwise spam the chat every 15 minutes until someone fixes it.
     */
    private function notifyBroken(): void
    {
        if (Cache::get(self::BROKEN_FLAG_CACHE_KEY)) {
            return;
        }

        Cache::forever(self::BROKEN_FLAG_CACHE_KEY, true);

        TelegraphChat::first()?->html(
            '⚠️ HelloFresh authentication is broken — I could not refresh the session token. '
            .'Please re-capture the apiV2Auth cookie and reseed it (see README.md).'
        )->send();
    }

    private function notifyRecoveredIfNeeded(): void
    {
        if (! Cache::pull(self::BROKEN_FLAG_CACHE_KEY)) {
            return;
        }

        TelegraphChat::first()?->html('✅ HelloFresh authentication is working again.')->send();
    }
}
