<?php

declare(strict_types=1);

return [
    'seb' => [
        'pending_session_ttl_minutes' => env('SEB_PENDING_SESSION_TTL_MINUTES', 15),
        'launch_token_ttl_seconds' => env('SEB_LAUNCH_TOKEN_TTL_SECONDS', 90),
        'grace_window_seconds' => env('SEB_GRACE_WINDOW_SECONDS', 45),
    ],
];
