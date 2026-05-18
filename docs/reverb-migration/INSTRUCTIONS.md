# Reverb Migration — Orchestrator Instructions

> **Agent:** You are the orchestrator. Read this entire document first, then delegate the frontend work to `FRONTEND.md` and execute the backend steps yourself.

## Overview

Migrate the CBT application from Pusher (external SaaS) to Laravel Reverb + Redis (self-hosted). The migration is **partially complete** — see Current State below.

## Why

Pusher's per-connection pricing scales poorly for live, concurrent exam environments. Reverb + Redis gives flat-cost, high-concurrency real-time infrastructure with no third-party rate limits.

## Current State

| Completed | Remaining |
|---|---|
| `laravel/reverb` installed (v1.10) | `pusher/pusher-php-server` still in `composer.json` |
| `config/broadcasting.php` has Reverb connection block (lines 38-49) | `pusher` connection block in broadcasting.php (lines 9-23) is dead code |
| `.env` has `BROADCAST_CONNECTION=reverb`, `REDIS_CLIENT=phpredis`, `QUEUE_CONNECTION=redis` | Pusher env vars (`PUSHER_*`, `VITE_PUSHER_*`) still present in `.env` and `.env.example` |
| Event classes all use driver-agnostic Laravel broadcasting (`ShouldBroadcast`, `PrivateChannel`) — no changes needed | `resources/js/echo.js` still configured for Pusher |
| `routes/channels.php` uses generic Laravel broadcasting — no changes needed | `.env.example` has duplicate `BROADCAST_CONNECTION` (line 38: `log`, line 47: `reverb`) and stale Pusher vars |

## Files That Need Zero Changes

Do not touch these files — they are driver-agnostic and work identically with Reverb:

- `app/Events/ExamSessionEnded.php`
- `app/Events/StudentSubmittedExam.php`
- `app/Events/StudentStartedExam.php`
- `app/Events/SuspiciousActivityDetected.php`
- `app/Events/ActivityFeedEvent.php`
- `routes/channels.php`
- Any other event classes implementing `ShouldBroadcast`

---

## STEP 1 — Remove Pusher PHP Server Dependency

**File:** `composer.json`

Remove line 19:
```
"pusher/pusher-php-server": "^7.2",
```

Then run:
```bash
composer remove pusher/pusher-php-server
```

Verify removal:
```bash
composer show | grep pusher
# Expected: no output
```

---

## STEP 2 — Clean Environment Files

### 2a. `.env` (production/local config)

Remove these lines entirely (lines 87-98):
```
PUSHER_APP_ID=2151655
PUSHER_APP_KEY=015208beeb13b818dd00
PUSHER_APP_SECRET=0c16bdc48afd650f5d22
PUSHER_APP_CLUSTER=ap2
PUSHER_PORT=443
PUSHER_SCHEME=https

VITE_PUSHER_APP_KEY="${PUSHER_APP_KEY}"
VITE_PUSHER_APP_CLUSTER="${PUSHER_APP_CLUSTER}"
VITE_PUSHER_HOST="${PUSHER_HOST}"
VITE_PUSHER_PORT="${PUSHER_PORT}"
VITE_PUSHER_SCHEME="${PUSHER_SCHEME}"
```

### 2b. `.env.example` (template for future devs)

1. Remove these lines:
```
BROADCAST_CONNECTION=log            <-- line 38, the first one (duplicate)
```

2. Remove the Pusher section (lines 55-59):
```
# Pusher (alternative)
PUSHER_APP_ID=
PUSHER_APP_KEY=
PUSHER_APP_SECRET=
PUSHER_APP_CLUSTER=mt1
```

3. Remove the VITE_APP_NAME line at the bottom (line 89):
```
VITE_APP_NAME="${APP_NAME}"
```
   (This is superseded by VITE_REVERB_* vars that go into FRONTEND.md)

4. Add the VITE_REVERB_* variables to the Reverb section (after line 53):
```
VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST="${REVERB_HOST}"
VITE_REVERB_PORT="${REVERB_PORT}"
VITE_REVERB_SCHEME="${REVERB_SCHEME}"
```

After cleanup, the `.env.example` Reverb section should look like:
```
# Broadcasting
BROADCAST_CONNECTION=reverb
REVERB_APP_ID=
REVERB_APP_KEY=
REVERB_APP_SECRET=
REVERB_HOST=localhost
REVERB_PORT=8080
REVERB_SCHEME=http

VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST="${REVERB_HOST}"
VITE_REVERB_PORT="${REVERB_PORT}"
VITE_REVERB_SCHEME="${REVERB_SCHEME}"
```

---

## STEP 3 — Clean Backend Broadcasting Config

**File:** `config/broadcasting.php`

Remove the `pusher` connection block (lines 9-23):
```php
'pusher' => [
    'driver' => 'pusher',
    'key' => env('PUSHER_APP_KEY'),
    'secret' => env('PUSHER_APP_SECRET'),
    'app_id' => env('PUSHER_APP_ID'),
    'options' => [
        'cluster' => env('PUSHER_APP_CLUSTER', 'mt1'),
        'host' => env('PUSHER_HOST') ?: null,
        'port' => env('PUSHER_PORT', 443),
        'scheme' => env('PUSHER_SCHEME', 'https'),
        'encrypted' => true,
        'useTLS' => env('PUSHER_SCHEME', 'https') === 'https',
    ],
    'client_options' => [],
],
```

Also update the default fallback to ensure it defaults to reverb:
```php
'default' => env('BROADCAST_CONNECTION', 'reverb'),
```

After cleanup, the file should contain only: `reverb`, `redis`, `log`, `null` connections.


## STEP 5 — Production Deployment Config (Render)

### 5a. Reverb as a separate service

Reverb runs as a **separate background WebSocket service** on Render, not inside the PHP-FPM web container.

Startup command:
```bash
php artisan reverb:start --host=0.0.0.0 --port=$PORT
```

- `$PORT` is Render's dynamically assigned port. Ensure this matches `REVERB_PORT` in the environment config.
- Render Health Check: point it to the Reverb service port. A simple TCP connect works.

### 5b. Trusted Proxies

Render sits behind a load balancer. Without trusted proxy config, channel authentication will see the wrong client IP.

In `bootstrap/app.php`, add:
```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->trustProxies(at: '*');
})
```

Or in the existing middleware configuration, ensure trusted proxies are set.

### 5c. CORS

The WebSocket server runs on a different port. CORS must allow the frontend origin to connect. This is configured in Reverb's own config (not Laravel's CORS middleware).

After publishing Reverb config (`php artisan vendor:publish --tag=reverb-config`), check `config/reverb.php` and set:
```php
'applications' => [
    [
        'id' => env('REVERB_APP_ID'),
        'key' => env('REVERB_APP_KEY'),
        'secret' => env('REVERB_APP_SECRET'),
        'allowed_origins' => [env('APP_URL')],  // <-- set this
    ],
],
```

### 5d. Sticky Sessions (if scaling to multiple Reverb instances)

If running multiple Reverb instances for high availability, configure Render's sticky sessions, or use the built-in `App\Providers\ReverbServiceProvider` for Redis-backed presence state.

---

## STEP 6 — Verification

After all changes are done, verify the migration:

```bash
# 1. No Pusher references remain
grep -ri "pusher" --include='*.{php,js,ts,vue,json,env}' . --exclude-dir=vendor --exclude-dir=node_modules
# Expected: only false positives in changelogs or comments, no active code

# 2. Composer is clean
composer show | grep pusher
# Expected: no output

# 3. Config cache is valid
php artisan config:clear
php artisan config:cache

# 4. Broadcasting routes are loaded
php artisan route:list | grep broadcast

# 5. Reverb config is valid
php artisan reverb:status
```

---

## Agent Handoff Summary

| Task | File | Agent |
|---|---|---|
| Remove composer dependency | `composer.json` | Orchestrator |
| Clean `.env` | `.env` | Orchestrator |
| Clean `.env.example` | `.env.example` | Orchestrator |
| Clean broadcasting config | `config/broadcasting.php` | Orchestrator |
| Rewrite Echo + frontend setup | `resources/js/echo.js` + `resources/js/app.js` | Frontend (see FRONTEND.md) |
| Production config (Render) | `config/reverb.php`, `bootstrap/app.php` | Orchestrator |
| Verification | — | Orchestrator |
