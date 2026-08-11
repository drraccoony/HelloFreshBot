# HelloFreshBot

A personal Telegram bot that messages you once a week with next week's HelloFresh
meals, and lets you pick your free add-on (if you haven't already) by tapping a button.

Built on Laravel + [`ricofresh/hellofresh-api`](vendor/ricofresh/hellofresh-api) (an
unofficial, reverse-engineered HelloFresh client — see its README for how auth works)
and [`defstudio/telegraph`](https://docs.defstudio.it/telegraph) for the Telegram side.

## How it works

- The actual "build the message" logic lives in `App\HelloFresh\WeeklyDigestSender`: it
  finds the Nth upcoming, not-yet-delivered week (1 = next week, up to 5), lists your
  currently selected meals, and either confirms the free add-on already set or sends an
  inline-keyboard message listing the available add-ons.
- `php artisan hellofresh:weekly-digest` (`app/Console/Commands/SendHelloFreshWeeklyDigest.php`)
  calls it for next week and is what's scheduled.
- In Telegram, `/remindme` (optionally `/remindme 2` through `/remindme 5`) calls the
  same sender on demand — see `App\Telegram\HelloFreshWebhookHandler::remindme()`.
- Tapping an add-on button hits Telegram's webhook → `HelloFreshWebhookHandler::setAddon()`,
  which calls `HelloFreshClient::setFreeAddon()` and edits the message to confirm.
- `routes/console.php` schedules the digest every Monday at 08:00 (server timezone), plus
  `php artisan hellofresh:keep-alive` every 15 minutes (`app/Console/Commands/KeepHelloFreshTokenAlive.php`)
  — a lightweight authenticated ping that keeps `HelloFreshClient`'s auto-refresh from
  ever going more than 15 minutes without touching the API, and alerts on Telegram (once,
  not repeatedly) if refreshing ever actually fails. The scheduler still needs a real cron
  entry (see below) — Laravel's scheduler doesn't run itself.
- Only chats registered as a `TelegraphChat` row can interact with the bot at all —
  unregistered senders get rejected outright (`allow_messages_from_unknown_chats` /
  `allow_callback_queries_from_unknown_chats` are both `false` in `config/telegraph.php`).
  This is also how access is restricted to specific people: register exactly the chat ids
  that should be allowed (a private chat's id equals that user's Telegram user id) via
  `php artisan telegraph:new-chat`. The scheduled digest still only messages the first
  registered chat, regardless of how many are allowed to interact.

## One-time setup

1. **Install dependencies** (already done in this checkout): `composer install`.

2. **Database**: this app uses SQLite (`database/database.sqlite`) since it's a
   single-user bot — no separate DB server needed. Already migrated; re-run
   `php artisan migrate` after pulling new migrations.

3. **HelloFresh credentials** — fill in `.env`:
   ```
   HELLOFRESH_EMAIL=you@example.com
   HELLOFRESH_PASSWORD=...
   HELLOFRESH_COOKIE=...   # see vendor/ricofresh/hellofresh-api/README.md — only used as a login() fallback
   ```
   Then seed a real session once (`login()` is Cloudflare-blocked; `refresh()` isn't —
   after this the bot should stay signed in indefinitely):
   ```bash
   php artisan tinker
   >>> app(\RicoFresh\HelloFreshApi\HelloFreshClient::class)->useTokens(
   ...     json_decode('<apiV2Auth cookie value from DevTools > Application > Cookies>', true)
   ... );
   ```

4. **Telegram bot** — create one via [@BotFather](https://t.me/BotFather), then:
   ```bash
   php artisan telegraph:new-bot
   # paste the bot token, name it, then say yes to "add a chat" and paste your own
   # chat id (message the bot once first, or use @userinfobot to find your id)
   ```
   `SendHelloFreshWeeklyDigest` sends the scheduled digest to the first `TelegraphChat`
   row; register additional chats (`php artisan telegraph:new-chat`) to let more people
   interact with the bot (see "Only chats registered..." above).

5. **Webhook** (needed for the add-on buttons to work) — requires this app to be
   reachable over public HTTPS:
   - Set `APP_URL` in `.env` to that public URL (or set `TELEGRAM_WEBHOOK_DOMAIN` if it
     differs from `APP_URL`).
   - Optionally set `TELEGRAPH_WEBHOOK_SECRET` to a random string — it's sent to Telegram
     and included as a header on incoming calls, but note the installed version of
     defstudio/telegraph (v1.72) doesn't actually verify it server-side; it's registered
     for forward-compatibility, not as real protection today.
   - Then register it with Telegram:
     ```bash
     php artisan telegraph:set-webhook
     ```
   - Sanity check: `php artisan telegraph:debug-webhook`.

6. **Scheduler cron** — add one line to the host's crontab so Laravel's scheduler (and
   therefore the weekly digest) actually fires:
   ```
   * * * * * cd /path/to/HelloFreshBot && php artisan schedule:run >> /dev/null 2>&1
   ```

## Manual test

```bash
php artisan hellofresh:weekly-digest
```

Sends next week's digest immediately instead of waiting for Monday. Or in Telegram,
send `/remindme` (next week) or `/remindme 2`..`/remindme 5` (further out) any time.
