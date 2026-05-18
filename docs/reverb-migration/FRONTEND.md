# Reverb Migration — Frontend Instructions

> **Agent:** You are the frontend developer. Follow these steps precisely. Do not modify backend files (`.env`, `composer.json`, `config/broadcasting.php`) — those are handled by the orchestrator.

## Overview

The app currently uses Laravel Echo with Pusher as the broadcast driver. We're pointing Echo at our self-hosted Laravel Reverb server instead. **Echo still uses the Pusher protocol** (via `pusher-js`), so the Echo config says `broadcaster: "pusher"` — but the host, port, and key point to Reverb instead of Pusher's cloud service.

## Current State

**File:** `resources/js/echo.js`

```js
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
window.Pusher = Pusher;

window.Echo = new Echo({
    broadcaster: "pusher",
    key: import.meta.env.VITE_PUSHER_APP_KEY,
    cluster: import.meta.env.VITE_PUSHER_APP_CLUSTER,
    forceTLS: true,
    wsHost: import.meta.env.VITE_PUSHER_HOST,
    wsPort: import.meta.env.VITE_PUSHER_PORT,
    wssPort: import.meta.env.VITE_PUSHER_PORT,
    enabledTransports: ["ws", "wss"],
});
```

**Problem:** All env vars reference `VITE_PUSHER_*` which will be deleted. The `cluster` option is Pusher-specific and Reverb ignores it. No `app.js` entry point exists.

## STEP 1 — Ensure Frontend Tooling Is Set Up

If no `package.json` exists at the project root, the frontend is not yet scaffolded. Create the minimum required files.

### 1a. Create `package.json` at project root

```json
{
    "private": true,
    "type": "module",
    "scripts": {
        "dev": "vite",
        "build": "vite build",
        "preview": "vite preview"
    },
    "devDependencies": {
        "vite": "^6.0.0",
        "@vitejs/plugin-vue": "^5.0.0",
        "laravel-vite-plugin": "^1.2.0",
        "vue": "^3.5.0"
    },
    "dependencies": {
        "laravel-echo": "^1.18.0",
        "pusher-js": "^8.4.0"
    }
}
```

### 1b. Create `vite.config.js` at project root

```js
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/js/app.js'],
            refresh: true,
        }),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
    ],
});
```

### 1c. Install dependencies

```bash
npm install
```

---

## STEP 2 — Rewrite `resources/js/echo.js`

Replace the entire file with this Reverb-aware configuration:

```js
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

window.Echo = new Echo({
    broadcaster: "pusher",
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST,
    wsPort: import.meta.env.VITE_REVERB_PORT,
    wssPort: import.meta.env.VITE_REVERB_PORT,
    wsPath: "",
    forceTLS: import.meta.env.VITE_REVERB_SCHEME === 'https',
    enabledTransports: ["ws", "wss"],
    cluster: "",
});
```

### Key Changes Explained

| Setting | Old (Pusher) | New (Reverb) | Why |
|---|---|---|---|
| `key` | `VITE_PUSHER_APP_KEY` | `VITE_REVERB_APP_KEY` | Reverb has its own app key |
| `cluster` | `VITE_PUSHER_APP_CLUSTER` | `""` (empty string) | Reverb doesn't use clusters. Empty string prevents Echo from appending `.cluster` to the host |
| `wsHost` | `VITE_PUSHER_HOST` | `VITE_REVERB_HOST` | Points to self-hosted server instead of Pusher cloud |
| `wsPort` | `VITE_PUSHER_PORT` | `VITE_REVERB_PORT` | Reverb's configured port |
| `wssPort` | `VITE_PUSHER_PORT` | `VITE_REVERB_PORT` | Same as wsPort — Reverb handles TLS at the server level |
| `forceTLS` | `true` (hardcoded) | `VITE_REVERB_SCHEME === 'https'` | Dynamic — `true` in production, `false` in local dev |
| `wsPath` | (not set) | `""` | Explicit default; avoids surprises with Echo path logic |

---

## STEP 3 — Create Entry Point `resources/js/app.js`

Create this file if it doesn't exist:

```js
import './echo';

// Bootstrap your Vue app here if needed
// import { createApp } from 'vue';
// import App from './App.vue';
// createApp(App).mount('#app');
```

This file is what Vite will build. It imports `echo.js` so Echo is initialized when the page loads.

---

## STEP 4 — Update Blade Layout to Load Vite

**File:** Ensure your main layout file (likely `resources/views/layouts/app.blade.php`) includes the Vite directive:

```blade
@vite(['resources/js/app.js'])
```

If the layout file doesn't exist yet, create one. If it uses a different entry point, update accordingly.

---

## STEP 5 — Verify

```bash
npm run build
# Expected: successful build with no errors
```

Check the built output includes the Reverb key, host, and port (not Pusher references).

---

## Frontend Scope of Work

| Task | File(s) | Status |
|---|---|---|
| Create package.json | `package.json` (root) | If missing |
| Create vite.config.js | `vite.config.js` (root) | If missing |
| Rewrite Echo config | `resources/js/echo.js` | Required |
| Create app.js entry | `resources/js/app.js` | If missing |
| Update Blade layout | `resources/views/**/*.blade.php` | Add @vite directive |
| Install deps | `npm install` | Required |

## Dependencies Summary

- `laravel-echo` — the client-side broadcast listener (keep, already used)
- `pusher-js` — the WebSocket protocol adapter (keep, Echo uses it to talk Pusher protocol to Reverb)
- `vite` + `laravel-vite-plugin` + `@vitejs/plugin-vue` — build tooling (may need to install)
