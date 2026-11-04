# Sincronizar el paquete de dominio al iniciar desarrollo

## Contexto
Después del merge, Finance en main usa clases ReadModels presentes en el paquete fuente pero ausentes en su copia de vendor. Composer instala el paquete path con symlink=false.
## Objetivo
Restaurar dashboard/cuentas y prevenir copias obsoletas al iniciar desarrollo.
## Alcance
Sincronización operativa y llamada al comando domain:sync existente antes de iniciar procesos Finance.
## Fuera de alcance
Cambiar reglas financieras, migrar datos o cambiar la estrategia Composer a symlinks.
## Comportamiento esperado
make dev y composer run dev sincronizan antes de PHP/Vite/queue; si falla, no arrancan servidores.
## Decisiones técnicas
Reutilizar composer run domain:sync en scripts/dev.sh. No actualizar dependencias externas.
## Componentes afectados
Launcher Finance y guía local.
## Backend
Solo preparación de vendor; sin lógica de negocio nueva.
## Frontend
Sin cambios.
## Persistencia
Sin cambios.
## API
Contratos intactos; recuperación de endpoints afectados.
## Validaciones
Comprobar carga de las cuatro clases, dashboard y cuentas con sesión local.
## Permisos y seguridad
No imprimir credenciales ni modificar datos financieros; respetar archivos locales del usuario.
## Casos límite
Sincronización fallida debe abortar antes de lanzar procesos.
## Estrategia de pruebas
Prueba shell supervisada con binarios ficticios: orden de sincronización y aborto al fallar. Validación navegador autenticado de lecturas reales.
## Estrategia de validación
Node tests, bash -n y git diff --check.
## Estrategia de commits
fix(dev): sync shared domain before starting Finance.
## Archivos estimados a modificar
apps/finance/scripts/dev.sh, scripts/finance-dev.test.mjs, docs/operations/local-development.md.
## Riesgos
La sincronización añade tiempo al arranque; no se ejecuta durante solicitudes HTTP.
## Preguntas abiertas
Ninguna.
## Plan de implementación
1. Confirmar fuente frente a vendor y sincronizar main.
2. Añadir preparación previa y pruebas de orden/fallo.
3. Verificar dashboard/cuentas y documentar.

## Resultado
Confirmada copia vendor obsoleta en main; sincronizada con composer run domain:sync. Las cuatro clases ReadModels cargan correctamente. Login y navegación autenticada al dashboard y listado de cuentas verificadas con Chrome DevTools: sin recursos HTTP fallidos; /api/accounts devuelve 200. No se modificaron datos financieros. El launcher sincroniza antes de PHP/Vite/queue; nueve pruebas del supervisor y arranque aprobadas, incluidos orden y aborto ante fallo.
