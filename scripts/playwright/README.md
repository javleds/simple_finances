# Capturas y verificaciones con Playwright

Herramientas locales para inspeccionar la SPA y comprobar comportamientos de interfaz. Los scripts de capturas sirven para documentación y planeación; los scripts `verify*` contienen aserciones con datos simulados. No constituyen una suite completa de flujos privados contra servicios reales.

## Requisitos

Desde la raíz Laravel, inicia la aplicación integrada:

```bash
composer run dev
```

Con Vite detenido, ejecuta `npm run build` antes de revisar cambios. Se requiere Chromium disponible para Playwright. Consulta [desarrollo local](../../docs/operations/local-development.md#assets-de-desarrollo-y-compilados) para distinguir HMR y assets compilados.

## Regresiones sin datos reales

```bash
npm run pw:mobile-design
node scripts/playwright/verifyVirtualBalanceChart.mjs
```

Estos scripts instalan sesiones ficticias, interceptan respuestas API y bloquean escrituras o endpoints sin fixture. No requieren crear usuarios ni proporcionar credenciales reales.

| Script | Alcance |
| --- | --- |
| `verifyMobileDesign.mjs` | 37 casos: suscripción fullscreen/selector largo, sheet de distribución y documentos legales embebidos e independientes en 360/390/430 px, alturas 640/844 y ambos temas; compatibilidad de encabezado desktop |
| `verifyVirtualBalanceChart.mjs` | 6 casos: gráfica virtual en tres anchos y ambos temas; proporciones y continuidad de segmentos, ganancias, pérdidas, saldo sin desglose, selector Físicas/Virtuales y reintento tras error |

Las comprobaciones fallan ante errores de página o solicitudes API inesperadas. Los detalles de la cobertura unitaria y sus límites están en [la estrategia de pruebas](../../docs/testing/strategy.md#diseño-móvil-documentos-legales-y-cuentas-virtuales).

## Capturas con una cuenta local

Los helpers de capturas usan una cuenta local existente. Los valores predeterminados son `test@example.com` y `password`; pueden sustituirse mediante variables de entorno. Esta configuración no aplica a los scripts de regresión con fixtures.

Listar vistas disponibles:

```bash
npm run pw:views:list
```

Capturar todas las vistas en móvil:

```bash
npm run pw:views -- --all
```

Capturar una vista o comparar móvil y escritorio:

```bash
npm run pw:views -- --facility account-transactions
npm run pw:views -- --facility dashboard --viewport both
```

Abrir el navegador visible sin escribir capturas:

```bash
npm run pw:views -- --facility transactions --headed --no-screenshot
```

Omitir rutas dinámicas que requieren cuentas o reglas existentes:

```bash
npm run pw:views:list -- --base-only
```

`pw:views` escribe en `tmp/playwright-views` por defecto. Los alias anteriores siguen disponibles:

```bash
npm run pw:facilities -- --all
```

Los alias escriben en `tmp/playwright-facilities` salvo que se configure `--output-dir` o `PW_SCREENSHOT_DIR`. No incorporar capturas, credenciales ni archivos temporales a commits.

## Variables de entorno

| Variable | Uso y valor predeterminado |
| --- | --- |
| `PW_APP_URL` | URL local de la SPA; `http://127.0.0.1:8000`. También aplica a los scripts `verify*` |
| `PW_API_URL` | URL de API para capturas; `http://127.0.0.1:8000/api` |
| `PW_EMAIL`, `PW_PASSWORD` | Credenciales locales para helpers de capturas; no necesarias para regresiones con fixtures |
| `PW_SCREENSHOT_DIR` | Directorio de salida de capturas |
