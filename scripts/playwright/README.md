# Playwright Facility Screenshots

Internal Playwright helpers for opening existing SPA facilities and taking screenshots.
These scripts are not an E2E test suite and are intentionally kept outside `src`.

## Prerequisites

Run the local backend and frontend:

```bash
cd ../api && php artisan serve
cd ../spa && npm run dev
```

The default login is `test@example.com` / `password`.

## Usage

List the available facility keys:

```bash
npm run pw:facilities -- --list
```

Shortcut:

```bash
npm run pw:facilities:list
```

Capture all facilities in mobile viewport:

```bash
npm run pw:facilities -- --all
```

Capture one facility:

```bash
npm run pw:facilities -- --facility account-transactions
```

Capture both mobile and desktop:

```bash
npm run pw:facilities -- --facility dashboard --viewport both
```

Open a visible browser without writing screenshots:

```bash
npm run pw:facilities -- --facility transactions --headed --no-screenshot
```

Skip dynamic routes that require existing accounts or distribution rules:

```bash
npm run pw:facilities -- --list --base-only
```

Screenshots are written to `tmp/playwright-facilities` by default.

## Environment Overrides

- `PW_APP_URL`: SPA URL. Defaults to `http://localhost:5173`.
- `PW_API_URL`: API URL. Defaults to `http://localhost:8000/api`.
- `PW_EMAIL`: login email. Defaults to `test@example.com`.
- `PW_PASSWORD`: login password. Defaults to `password`.
- `PW_SCREENSHOT_DIR`: screenshot output directory.
