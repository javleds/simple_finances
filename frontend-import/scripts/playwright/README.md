# Playwright View Screenshots

Internal Playwright helpers for opening existing SPA views and taking screenshots.
These scripts are for product documentation and feature planning, not an E2E test suite, and are intentionally kept outside `src`.

## Prerequisites

Run the local backend and frontend:

```bash
cd ../api && php artisan serve
cd ../spa && npm run dev
```

The default login is `test@example.com` / `password`.

## Usage

List the available view keys:

```bash
npm run pw:views:list
```

Capture all available views in the mobile viewport:

```bash
npm run pw:views -- --all
```

Capture one view:

```bash
npm run pw:views -- --facility account-transactions
```

Capture both mobile and desktop:

```bash
npm run pw:views -- --facility dashboard --viewport both
```

Open a visible browser without writing screenshots:

```bash
npm run pw:views -- --facility transactions --headed --no-screenshot
```

Skip dynamic routes that require existing accounts or distribution rules:

```bash
npm run pw:views:list -- --base-only
```

Screenshots are written to `tmp/playwright-views` by default when using `pw:views`.

The older `pw:facilities` scripts are still available as compatibility aliases:

```bash
npm run pw:facilities -- --all
```

Those compatibility commands write to `tmp/playwright-facilities` unless `--output-dir` or `PW_SCREENSHOT_DIR` is set.

## Environment Overrides

- `PW_APP_URL`: SPA URL. Defaults to `http://localhost:5173`.
- `PW_API_URL`: API URL. Defaults to `http://localhost:8000/api`.
- `PW_EMAIL`: login email. Defaults to `test@example.com`.
- `PW_PASSWORD`: login password. Defaults to `password`.
- `PW_SCREENSHOT_DIR`: screenshot output directory.
