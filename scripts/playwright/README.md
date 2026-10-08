# Capturas de vistas con Playwright

Helpers internos para abrir vistas de la SPA y capturarlas. Sirven para documentación de producto y planeación; no constituyen una suite E2E y permanecen fuera de `resources/js`.

## Requisitos

Desde la raíz Laravel, inicia la aplicación integrada:

```bash
composer run dev
```

Los scripts usan `test@example.com` / `password` por defecto; crea esa cuenta localmente o cambia las credenciales.

## Uso

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

## Variables de entorno

- `PW_APP_URL`: SPA URL. Defaults to `http://127.0.0.1:8000`.
- `PW_API_URL`: API URL. Defaults to `http://127.0.0.1:8000/api`.
- `PW_EMAIL`: login email. Defaults to `test@example.com`.
- `PW_PASSWORD`: login password. Defaults to `password`.
- `PW_SCREENSHOT_DIR`: screenshot output directory.
