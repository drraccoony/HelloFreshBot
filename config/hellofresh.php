<?php

declare(strict_types=1);

return [
    'base_uri' => env('HELLOFRESH_BASE_URI', 'https://www.hellofresh.com/gw'),

    'email' => env('HELLOFRESH_EMAIL'),
    'password' => env('HELLOFRESH_PASSWORD'),

    // Raw browser Cookie header, needed to get login()/refresh() past Cloudflare (a
    // cookie-less request gets a challenge page instead of JSON). Capture it from
    // DevTools > Network > any request to hellofresh.com > Request Headers > cookie.
    // It expires periodically — expect to refresh it occasionally. See README.md.
    'cookie' => env('HELLOFRESH_COOKIE'),

    'country' => env('HELLOFRESH_COUNTRY', 'US'),
    'locale' => env('HELLOFRESH_LOCALE', 'en-US'),

    // The menus-service "product" catalog key matching your box type, used to scope
    // getMenuForWeek()/getUpcomingMeals() instead of returning the entire week's catalog
    // (recipes + every add-on). "classic-menu" is confirmed for a Classic Box plan; check
    // getSubscriptions() / getMenuForWeek($week, ['product' => null]) if yours differs.
    'menu_product' => env('HELLOFRESH_MENU_PRODUCT', 'classic-menu'),

    // How long a fetched token pair is cached for (seconds). Access tokens
    // themselves expire much sooner; the client refreshes them automatically.
    'token_cache_ttl' => env('HELLOFRESH_TOKEN_CACHE_TTL', 2_160_000),

    'routes' => [
        'enabled' => env('HELLOFRESH_ROUTES_ENABLED', true),
        'prefix' => env('HELLOFRESH_ROUTES_PREFIX', 'api/hellofresh'),
        'middleware' => ['api'],
    ],
];
