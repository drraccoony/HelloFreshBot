<?php

use Illuminate\Support\Facades\Schedule;

// Every Monday morning: what's coming next week, and a free add-on picker if needed.
Schedule::command('hellofresh:weekly-digest')
    ->weeklyOn(1, '08:00')
    ->timezone(config('app.timezone'))
    ->onOneServer();

// Keep the HelloFresh session token from ever going stale between digests, and catch
// a broken token within 15 minutes instead of only finding out next Monday.
Schedule::command('hellofresh:keep-alive')
    ->everyFifteenMinutes()
    ->onOneServer()
    ->withoutOverlapping();
