# Configuración por aplicación

## Contexto
El stack mezcla configuración de aplicaciones en `.env.chat` con la de Finance.
## Objetivo
Un `.env` por aplicación y uno raíz exclusivamente para infraestructura.
## Alcance
Compose, launcher, ejemplos y documentación de arranque.
## Fuera de alcance
Cambios de dominio, despliegue, rotación de credenciales existentes y migración automática de datos.
## Comportamiento esperado
Finance, MCP y agentes cargan sus propios archivos. La raíz aprovisiona bases y puertos. `chat-config` crea archivos faltantes y sincroniza credenciales vacías sin sobrescribir valores existentes; rechaza conflictos. `.env.chat` existente requiere migración manual explícita.
## Decisiones técnicas
Reutilizar `env_file` de Compose y `scripts/chat.mjs`. El migrador usa exclusivamente la credencial PostgreSQL propietaria desde infraestructura. API/worker usan DATABASE_URL del agente. Los valores de aplicación no se sobreescriben desde Compose local.
## Componentes afectados
Ejemplos env, overlays Compose, scripts, guía operativa.
## Backend
Solo configuración de procesos, sin cambios de lógica.
## Frontend
Sin cambios.
## Persistencia
Sin migraciones; mantener passwords al reutilizar volúmenes.
## API
Sin cambios de contratos.
## Validaciones
Validar archivos y credenciales por propietario antes de arrancar. Aprovisionar MCP con usuario fijo finance_mcp y contraseña hexadecimal generada.
## Permisos y seguridad
Archivos creados con modo 0600. No imprimir secretos ni compartir claves del modelo con MCP/Finance. No leer ni migrar secretos reales automáticamente.
## Casos límite
Archivos parciales, configuraciones existentes incompatibles, contraseña root previa, modelos sin configurar y archivos heredados.
## Estrategia de pruebas
Pruebas Node sobre directorios temporales: inicialización, repetición, preservación y conflictos. Renderizar Compose local y producción con credenciales ficticias.
## Estrategia de validación
Node test/check, Compose config y git diff --check.
## Estrategia de commits
Un commit coherente `refactor(config): separate infrastructure and application environments`, incluyendo pruebas y documentación.
## Archivos estimados a modificar
`.env.example`, ejemplos de apps, `docker-compose.chat*.yml`, `scripts/chat*.mjs`, docs operativas.
## Riesgos
Volúmenes existentes no actualizan sus passwords automáticamente. Finance existente puede usar DB host local; documentar adaptación a Docker en su propio env.
## Preguntas abiertas
Ninguna para implementar; modelo/clave siguen bajo configuración del usuario.
## Plan de implementación
1. Separar env_file de servicios y ejemplos, manteniendo redes y URLs privadas; validar composición.
2. Actualizar inicializador y launcher con preservación de valores; probar sobre archivos ficticios.
3. Actualizar guía y referencias, registrar resultados y commit.

## Resultado
Separación implementada y guía de migración publicada. Cuatro pruebas Node aprobadas (inicialización/repetición, preservación, conflictos y legado); sintaxis y diff correctos. Compose local y producción renderizados con archivos temporales y credenciales ficticias: aplicaciones aisladas, migrador sin tokens de servicio y servicios internos sin puertos publicados. No se modificaron archivos privados ni bases existentes.
