# Validación de la integración SPA

## Comprobaciones realizadas

- Historial: los commits originales `5afa6e6` de Laravel y `1475175` de Vue son antecesores del HEAD integrado.
- `composer validate --no-check-publish` y `composer install`: correctos.
- `npm ci`, `npm run type-check` y `npm run build`: correctos.
- Backend: 151 pruebas, 962 assertions, sin fallos.
- Cliente integrado: tres pruebas nuevas correctas para `/api`, override y redirección 401 independiente de `/build`.
- `node --test scripts/publish-assets.test.mjs`: tres pruebas correctas de publicación, conservación de assets/manifest anterior y rechazo de builds incompletos o rutas externas.
- `php artisan route:cache` y `route:clear`: correctos.
- Compose: validación estructural, hosts y condición de MySQL saludable correctas; no se levantó infraestructura de producción.
- `node scripts/check-spa-integration.mjs --hmr`: navegador móvil con entrada Laravel, estilos y actualización CSS por websocket correctos.
- `node scripts/check-spa-integration.mjs`: mismo flujo usando archivos compilados con Vite detenido.
- `composer run dev`: inicio PHP/Vite correcto; Ctrl+C libera ambos puertos y retira `public/hot`; puerto PHP ocupado termina con error y detiene Vite.
- Verificación firmada: enlaces de `api.finsi.com` y `fin-si.com` válidos con redirección al frontend integrado.

## Fallos previos conservados

La suite Vue completa ya tenía tres fallos en `transactionsRepository.spec.ts`: expectativas desactualizadas para `ledgerRows` y `actionType`. La suite integrada conserva los mismos tres fallos; 41 de 44 pruebas pasan. No se cambió el parser financiero para acomodar esas expectativas.

`composer format:check` falla por una regla previa no admitida en `.php-cs-fixer.dist.php`: `align_single_space_minimal_by_indent`. Los archivos PHP afectados pasan comprobación de sintaxis. PHPStan no está instalado aunque existe el script `composer stan`.

## Repetición

Ejecutar las comprobaciones Node y PHP desde la raíz Laravel. Para HMR iniciar `composer run dev`; para el build ejecutar `npm run build` y arrancar solo `php artisan serve --host=127.0.0.1 --port=8000 --tries=1`. El smoke admite `PW_APP_URL` para cambiar la dirección.

El smoke no crea usuarios ni transacciones. Login, recuperación, invitaciones, saldos, custodia y reembolsos tienen cobertura backend existente. La comprobación de producción real, la transición de routers y el archivado del repositorio SPA quedan como pasos operativos del despliegue documentado.
