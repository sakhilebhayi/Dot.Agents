<?php

use App\Sso\AgentsProvisioner;

/*
 * Dot.Agents' own choices for the shared sign-in package (packages/dot-sso-client). Everything else, including
 * DOTSSO_ENABLED (default false), comes from the package's defaults and the host .env.
 */
return [
    'provisioner' => AgentsProvisioner::class,

    // Agents has always created an account for a person InfoDot vouches for (INFODOT_PROVISION_USERS), so that stays.
    'auto_provision' => (bool) env('DOTSSO_AUTO_PROVISION', env('INFODOT_PROVISION_USERS', true)),

    // Agents' signed-in routes use `auth:sanctum`, which makes `sanctum` the default guard for the rest of that request;
    // the shared package must always act on the session guard (login, logout, revocation), so it is named outright.
    'guard' => 'web',

    'landing' => '/dashboard',

    // A deep link may open any signed-in page of the app, and nothing else.
    'deep_link' => [
        'allow' => ['#^/(dashboard|search|marketplace|my-agents|runs|usage|workflows|governance|security|organization|billing|settings|social|invitations|consent|user/profile|teams)(/|\?|$)#'],
        'deny' => ['#^/(sso|login|register|logout|forgot-password|reset-password|auth|oauth|two-factor-challenge|passkeys|user/confirm)(/|\?|$)#i'],
    ],

    // Never intercepted. Agents' machine doors are all outside the pages the package takes over: POST /hooks/{uuid}
    // and /api/v1/* (Sanctum, `api/*`), the Stripe and social webhooks (`webhooks/*`, `social/webhooks/*`), the
    // third-party connection OAuth (`auth/{platform}/*`), /up and /guardian. Nothing extra to list.
    'allow_paths' => [],
];
