Plan para instalar y configurar Rector con reglas Laravel

1. Instalar dependencias dev: `rector/rector` y `rector-laravel/rector-laravel`.
2. Crear `rector.php` apuntando a `app`, `database`, `routes`, `tests`, con preset Laravel, PHP 8.2 y exclusiones de cachés/generados.
3. Añadir comandos Composer: `composer rector` (process) y `composer rector:dry` (`--dry-run --diff`).
4. Habilitar sets iniciales: Laravel, dead code, type declarations, return types y nullable strictness; dejar reglas agresivas listas pero opt-in.
5. Probar con `composer rector:dry` en un scope pequeño (ej. `app/Actions`) y revisar diffs.
6. Ajustar configuración: `skip` para casos problemáticos y nota rápida en README interno sobre cómo ejecutar y aceptar cambios.
