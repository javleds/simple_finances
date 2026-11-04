# Monorepo inicial y paquete compartido de modelos y permisos

## Contexto

Estado: implementación local realizada; validación y cierre en curso. No se ha ejecutado despliegue remoto.

Laravel 12 y la SPA Vue/Vite están integrados en la raíz. Laravel sirve `resources/views/spa.blade.php`; Vite compila `resources/js/main.ts`. Composer, npm, scripts, Docker y pruebas asumen actualmente esa raíz.

La autorización efectiva está distribuida entre controladores, consultas y servicios. `AccountPolicy::view`, `TransactionPolicy::view` y `FinancialGoalPolicy::view` retornan `true`; copiarlas no reproduce el aislamiento actual. `AuthorizeAccountAccess`, `AuthorizeUserOwnedResource`, `VisibleAccountsForUser` y `AuthorizeAttachmentAccess` contienen parte de las restricciones reales. Categorías, invitaciones y otros recursos incluyen controles locales adicionales.

Aunque las instrucciones históricas mencionan scopes globales por usuario, no se encontraron registros `addGlobalScope` en los modelos y traits inspeccionados. La extracción debe basarse en las consultas y controles efectivos, sin asumir aislamiento automático ni agregar un scope global universal.

Existen dependencias inversas que impiden mover modelos sin adaptación: `Account::updateBalance()` llama a `RecalculateAccountBalance`; `AccountInvite` y `Subscription` despachan eventos de `App`; varios hooks de creación y `AccountInvite::isOwnerAccount()` consultan `auth()`.

## Objetivo

Preparar un monorepo con la aplicación existente en `apps/finance` y un paquete Composer en `packages/finance-domain`, que reúna todos los modelos actuales, sus dependencias de modelado y las reglas de autorización existentes. Finance debe consumir ese paquete y conservar sus contratos, comportamiento financiero y SPA integrada.

El paquete debe poder utilizarse desde otra aplicación Laravel para consultas autorizadas sin cargar código de `apps/finance`. La futura aplicación MCP comenzará con lectura, pero no se crea en este alcance.

## Alcance

- Mover la aplicación Laravel/Vue a `apps/finance`, incluidos manifiestos, locks, configuración, pruebas y herramientas propias.
- Crear `packages/finance-domain`, con nombre Composer `fin-si/finance-domain` y namespace `FinSi\FinanceDomain`.
- Extraer todos los modelos de `app/Models`, incluidas entidades auxiliares de integraciones, para evitar relaciones hacia modelos locales de Finance.
- Extraer enums y traits que los modelos necesitan, policies y reglas de acceso, incluidas las restricciones de visibilidad de listados, relaciones y agregados.
- Mantener compartidas las decisiones actuales de autorización de escritura; ejecutar y orquestar escrituras seguirá siendo responsabilidad de Finance.
- Actualizar consumidores PHP, autenticación, factories, migraciones que referencian clases, pruebas, scripts y configuración operativa.
- Mantener la propiedad de migraciones, seeders, Actions, jobs y efectos externos en Finance.

## Fuera de alcance

- Crear aplicaciones, carpetas de reserva, imágenes o endpoints de MCP, agentes, FastAPI o LangGraph.
- Incorporar PostgreSQL, cambiar MySQL o modificar el esquema financiero.
- Extraer la SPA, introducir workspaces npm, Nx, Turborepo o un gestor adicional de monorepo.
- Compartir Actions, DTOs de casos de uso, cálculos de dashboard, orquestadores o integraciones.
- Cambiar reglas de negocio, actualizar dependencias ajenas al objetivo o rediseñar los eventos existentes.
- Ejecutar SSH, despliegues remotos o migraciones en producción.

## Comportamiento esperado

La aplicación mantiene URLs, JWT, formatos JSON, códigos de error, paginación, filtros, vistas, assets, notificaciones y reglas financieras. Los comandos de aplicación se ejecutan desde `apps/finance`; el despliegue conserva una entrada `deploy.sh` en la raíz.

Una consulta compartida exige un actor explícito y aplica las mismas restricciones que Finance. Cambiar de usuario entre dos ejecuciones en el mismo proceso no conserva permisos ni resultados del anterior. Tener acceso a una cuenta compartida no concede acceso a los datos privados de sus miembros.

Compartir modelos Eloquent no convierte todas sus consultas en consultas autorizadas. El contrato para consumidores exige usar las reglas de acceso y consultas de visibilidad compartidas; el futuro MCP deberá además usar credenciales de base de datos de solo lectura. No se implementa ese consumidor ahora.

## Decisiones técnicas

### Estructura

```text
apps/
  finance/
    app/                     # HTTP, Actions/Services, jobs, eventos, adaptadores
    bootstrap/
    config/
    database/                # Migraciones, factories y seeders
    public/
    resources/js/            # Vue/Vite permanece integrado
    routes/
    scripts/                 # Desarrollo, assets y pruebas operativas
    storage/
    tests/
    composer.json
    composer.lock
    package.json
    package-lock.json
    artisan
    .env.example
packages/
  finance-domain/
    src/
      Models/
      Enums/
      Traits/
      Authorization/         # Reglas explícitas por actor y recurso
      Policies/              # Adaptadores Laravel a las mismas reglas
      Queries/               # Restricciones reutilizables de visibilidad
      FinanceDomainServiceProvider.php
    tests/
    composer.json
docker/                      # Infraestructura actual ajustada al monorepo
docker-compose.yml
docker-compose.prod.yml
deploy.sh
docs/
AGENTS.md
```

Mantener Compose y `docker/` en la raíz evita una segunda reorganización de infraestructura sin beneficio inmediato. Mantener también allí metadatos Git e instrucciones generales. Mover configuraciones Vite, Vitest, TypeScript, ESLint, PHP y Node con la aplicación. Adaptar los enlaces de `resources/js/AGENTS.md` y la documentación vigente; no reescribir documentos históricos.

### Composer y dirección de dependencias

- Finance declara un repositorio Composer de tipo `path` hacia `../../packages/finance-domain` y una versión local explícita y estable para la resolución del paquete, sin depender del nombre de la rama Git.
- El lock de Finance fija la resolución. Actualizar exclusivamente el paquete y dependencias imprescindibles; no ejecutar una actualización general.
- Usar instalación por copia del paquete como configuración inicial reproducible también en producción. Añadir un comando de sincronización local del paquete para desarrollo que no actualice dependencias externas; probar que cambios del paquete llegan a `vendor`.
- El paquete depende de componentes Illuminate necesarios y PHP compatible con Finance; no depende de `App`, de rutas de Finance, de sus helpers ni de su contenedor configurado.
- Registrar policies explícitamente en el provider del paquete. No depender de descubrimiento por namespace `App`.
- El provider compartido no registra rutas, migraciones, envíos, jobs ni listeners de negocio.
- `Model::unguard()` permanece como decisión de Finance. Pruebas del paquete no pueden depender de ese estado global; cada escritura de Finance sigue validándose y autorizándose.

## Componentes afectados

| Componente actual | Destino o adaptación |
| --- | --- |
| Todos los archivos de `app/Models` | `packages/finance-domain/src/Models` |
| Enums usados por modelos y reglas compartidas | `src/Enums`; enums exclusivos de integraciones permanecen en Finance |
| Traits de relaciones y enums usados por el paquete | `src/Traits`, sin dependencia del actor HTTP |
| `app/Policies/*` | `src/Policies`, delegando en reglas efectivas |
| `AuthorizeAccountAccess`, `AuthorizeUserOwnedResource` | `src/Authorization`, actor obligatorio |
| `VisibleAccountsForUser` | `src/Queries`, preservando exclusión de archivadas y variante histórica explícita |
| `AuthorizeAttachmentAccess` | Regla compartida de lectura/gestión; almacenamiento y procesamiento quedan en Finance |
| Restricciones de categorías e invitaciones en controladores | Reglas y visibilidad compartidas, manteniendo diferencias entre rutas globales y anidadas |
| `AuthorizeTransactionParticipants` | Compartir predicados de permiso/membresía; validación del payload y excepciones históricas de la operación permanecen en Finance |
| Controladores, requests, servicios, jobs, comandos, eventos | Actualizar imports y consumir reglas compartidas |
| `config/auth.php`, middleware JWT, provider de aplicación | Usar el `User` compartido y registrar adaptaciones locales |

El inventario de permisos debe cubrir todas las rutas autenticadas y otros puntos de entrada existentes; esta tabla no sustituye ese inventario.

## Backend

### Desacoplar los modelos

1. Sustituir llamadas a `Account::updateBalance()` por la Action `RecalculateAccountBalance` en Finance, incluidos factories de escenarios y pruebas. Retirar el método del modelo compartido; no mover la Action ni introducir un contrato solo para conservar ese atajo.
2. Mover el enlace de eventos de `AccountInvite` y `Subscription` a un provider local de Finance mediante callbacks de ciclo de vida equivalentes. Conservar eventos, listeners, orden, semántica de cancelación y frecuencia; no introducir una nueva orquestación en este cambio.
3. Mover la asignación implícita de `user_id` basada en `auth()` a hooks locales de Finance. Preservar las diferencias actuales: `Transaction` solo completa el usuario si está vacío; algunos traits lo reemplazan. Cubrir cada comportamiento antes de cambiarlo.
4. Convertir controles como `isOwnerAccount()` en reglas que reciben un actor. Mantener su significado efectivo caracterizado, sin inferir permisos por el nombre del método.
5. Mantener casts, nombres de tablas, relaciones, pivots, atributos ocultos, soft deletes, accessors y cálculos puros actuales. Evitar dos clases de modelo activas para la misma entidad.
6. Mantener factories en Finance y configurar allí la resolución explícita para los namespaces nuevos; el paquete no debe importar factories de la aplicación.

### Compatibilidad de nombres persistidos

El cambio de namespace también puede afectar datos existentes: existe `notifications.notifiable_type` y pueden existir modelos serializados en colas. Inventariar morphs, payloads de jobs y enums serializados antes de retirar nombres antiguos.

Conservar los identificadores morph existentes mediante un mapa explícito. Si hay payloads pendientes con clases antiguas, habilitar aliases de compatibilidad exclusivamente en Finance, cargados antes de deserializar, con prueba de un payload anterior. No conservar subclases duplicadas como modelos alternativos. La retirada posterior de aliases requiere comprobar que no quedan payloads ni referencias persistidas; no vaciar colas como solución.

## Frontend

Mover Vue/Vite con Finance sin alterar componentes, router, Pinia ni TanStack Query. Mantener `resources/js/main.ts`, Blade, alias de Vite, `/api`, `public/build` y URL local. Ajustar únicamente rutas de herramientas y documentación que atraviesen la nueva raíz. No se crean componentes visuales.

## Persistencia

Finance sigue siendo el único propietario y ejecutor de migraciones MySQL. El paquete define mapeos Eloquent, no instala ni migra tablas al arrancar. No introducir migraciones ni copiar datos como consecuencia de mover código.

Conservar `APP_KEY`, credenciales, datos, storage privado, enlaces públicos, logs y material operativo no versionado. No mover ciegamente el contenido local mediante un glob: distinguir archivos versionados, dependencias regenerables y datos persistentes. Nunca agregar `.env`, adjuntos, dumps o `vendor` a Git.

## API

No se agregan endpoints. Finance conserva los controladores y serialización actuales. El transporte autentica y entrega el actor al paquete; el paquete decide el permiso sin leer el request, JWT ni un singleton de identidad mutable.

Las reglas pueden devolver booleanos o respuestas de autorización Laravel; los adaptadores preservan los 403/404 actuales. Los casos de recurso ajeno, recurso inexistente y relación anidada incorrecta se prueban por separado. Las policies de lectura dejan de retornar `true` incondicionalmente y utilizan la regla efectiva que ya protege cada endpoint.

## Validaciones

- Identidad obligatoria en reglas y consultas de visibilidad; ausencia de actor nunca implica acceso global.
- Separar filtros funcionales (fecha, búsqueda, paginación) de restricciones de seguridad. Los primeros permanecen en Actions/queries de Finance; las segundas se comparten y se aplican antes de paginar o agregar.
- Validación de payload, reglas de negocio y cálculos permanecen en Finance, incluso si el nombre de la clase incluye `Authorize`.
- Mantener controles de pertenencia entre cuenta, transacción, objetivo, adjunto e invitación.
- Evitar aplicar el permiso general de una entidad a una ruta que actualmente exige otro permiso más restrictivo.

## Permisos y seguridad

Construir una matriz de ruta/recurso, regla, actor, consulta, estado y código de denegación, usando `EndpointAuthorizationTest` como punto de partida. Cubrir como mínimo:

| Familia | Reglas a extraer o caracterizar |
| --- | --- |
| Cuentas, miembros, libro, snapshots y diagnósticos | Membresía, propiedad cuando aplique, cuenta activa y relación anidada |
| Transacciones, asignaciones y reembolsos | Lectura por acceso a la cuenta; autoría y otras restricciones actuales para mutar |
| Adjuntos | Membresía vigente, cuenta activa, transacción correcta y adjunto no eliminado |
| Categorías | Catálogo personal del actor o catálogo compartido de la cuenta; administración según regla actual |
| Invitaciones | Destinatario por correo, propietario o miembro según endpoint; no unificar esas capacidades |
| Metas, ingresos/egresos fijos, suscripciones y pagos | Propiedad y pertenencia efectiva según endpoint y relaciones |
| Dinero personal | Propiedad privada; membresía compartida no concede acceso a configuración, vínculos ni reembolsos privados |
| Perfil, aceptación legal, ajustes y notificaciones | Usuario propietario; catálogos generales conservan su alcance actual |
| Telegram, WhatsApp y registros técnicos | Propiedad o acceso interno explícito; compartir un modelo no concede exposición de secretos ni payloads |
| Dashboard, búsquedas y opciones de filtros | Restricciones compartidas antes de contar, sumar o devolver opciones |

Separar reglas de acceso de los formatos expuestos: ocultar contraseña no basta para convertir `User::toArray()` en respuesta segura para todos los consumidores. Conservar los DTOs/serializadores actuales de Finance; ningún futuro consumidor queda autorizado a exponer todos los campos por importar un modelo.

JWT, rate limiting, firma de webhooks y middleware HTTP permanecen en Finance. Si la aceptación legal contiene una decisión reutilizable de acceso, extraer el predicado; conservar redirecciones, respuesta HTTP y carga de documentos en la aplicación. No crear un nuevo sistema de roles o tablas de permisos.

## Casos límite

- Usuario propietario que no aparece en el pivot: preservar la distinción real entre propiedad y membresía.
- Miembro retirado, cuenta archivada y acceso a instantáneas personales históricas sin acceso al detalle compartido actual.
- Pagador distinto del autor; participantes históricos permitidos al editar y permisos de reembolso.
- Listados vacíos, agregados y filtros que podrían revelar datos de terceros aunque el detalle esté protegido.
- Dos actores consecutivos en un mismo proceso y consultas sin sesión HTTP.
- Jobs, comandos y migraciones sin usuario autenticado: acceso interno explícito, sin interpretar la ausencia de actor como administrador.
- Callbacks de creación con usuario explícito, ausencia de usuario y eventos que cancelan una operación.
- Restauración de colas/notificaciones con nombres antiguos y construcción de factories tras cambiar namespace.

## Estrategia de pruebas

1. Caracterizar permisos actuales antes de extraerlos con pruebas HTTP de lectura y mutación; ampliar las existentes donde falten casos de aislamiento.
2. Probar el paquete con un host mínimo Laravel de pruebas, sin cargar `apps/finance`. Usar Orchestra Testbench como dependencia exclusiva de desarrollo, en versión compatible con Laravel 12, para probar provider, Eloquent y policies; esta dependencia demuestra que el paquete funciona sin la aplicación.
3. Mantener fixtures de esquema mínimos para las pruebas independientes, sin tratarlos como migraciones productivas. Complementar con pruebas de integración usando las migraciones reales de Finance para detectar diferencias.
4. Pruebas compartidas por recurso: propietario, miembro, tercero, actor ausente, cuenta archivada, relación incorrecta y cambio de actor; verificar detalle, listas y agregados.
5. Añadir una comprobación de arquitectura que rechace imports/referencias de runtime desde el paquete hacia `App`, rutas/helpers de Finance y resolución del actor mediante `auth()`/request. Comprobar que el paquete no contiene Actions de negocio.
6. Reutilizar las suites `EndpointAuthorizationTest`, `SharedAccountParticipantsTest`, `TransactionParticipantAuthorizationTest`, `CategoryEndpointTest`, `TransactionAttachmentsTest`, `PersonalMoneyEndpointTest`, `DashboardEndpointsTest`, `SharedAccountTransactionLifecycleTest` y pruebas de servicios relacionadas.
7. Probar los hooks locales, compatibilidad morph/colas, descubrimiento de factories, login JWT, recuperación de contraseña, invitaciones y pagos de suscripciones.
8. Adaptar `scripts/deploy.test.mjs`, `publish-assets.test.mjs` y `check-spa-integration.mjs` a la estructura nueva. Probar instalación limpia y consumo del paquete desde Compose, además de ejecución en host.

## Estrategia de validación

Registrar una línea base antes de la migración y distinguir fallos preexistentes de regresiones. Ejecutar por etapa las pruebas afectadas; antes de cerrar la implementación:

```bash
cd apps/finance
composer validate --strict
composer install
php artisan test
npm ci
npm run type-check
npm run test:unit -- --run
npm run build
composer run format:check
node --test scripts/deploy.test.mjs scripts/publish-assets.test.mjs
node scripts/check-spa-integration.mjs
```

Añadir y ejecutar `composer test` en el paquete con su configuración independiente. Validar ambos manifiestos Composer y arranque de Finance con el paquete instalado. Los scripts `stan` y `pint` aparecen en Composer, pero sus herramientas no figuran en las dependencias inspeccionadas: no declararlos comprobados ni instalarlos por este refactor; usar herramientas realmente disponibles.

Desde la raíz, comprobar sintaxis shell, ambos Compose con `config --quiet` usando valores locales de prueba y un arranque local contra MySQL. Validar login, rutas profundas Vue, assets, lectura autorizada, mutación financiera y procesamiento de colas. La suite SQLite actual se conserva; las verificaciones MySQL detectan diferencias de motor en consultas de permisos.

## Configuración y operación Docker

- Conservar Compose en la raíz y usar esa raíz como contexto de build para que el paquete sea accesible.
- Montar el checkout en `/workspace` y usar `/workspace/apps/finance` como `working_dir` de PHP, Node y workers; ajustar Nginx, FastCGI y rutas estáticas al mismo layout.
- Resolver `.env` de aplicación desde `apps/finance/.env` mediante `--env-file` y `env_file` explícitos; verificar las diferencias entre interpolación Compose y entorno del contenedor.
- Mantener proyecto Compose, volúmenes, redes, contenedores y routers existentes. No adivinar el nombre efectivo de producción: exigir la configuración existente en la futura transición y fallar si no se puede identificar inequívocamente.
- Mantener `deploy.sh` en raíz, distinguir raíz Git y raíz de aplicación, y conservar mantenimiento antes de actualizar un checkout montado, publicación atómica de assets, healthchecks y diagnóstico de adjuntos.
- Diseñar la primera transición por separado: el script antiguo en ejecución no se reemplaza a sí mismo tras `git pull`. Preparar una entrada de migración local y ensayada que detenga consumidores usando el layout anterior, preserve storage/secretos, actualice código y arranque el nuevo layout en mantenimiento. No ejecutar esa transición remotamente en esta tarea.
- Regenerar dependencias/caches y reconstruir assets en las rutas nuevas. Conservar rollback conjunto de código, mounts, storage y manifest; no basta revertir Git si los directorios persistentes cambiaron.

## Estrategia de commits

| Orden y mensaje convencional | Contenido y documentación del mismo commit | Comprobaciones antes de confirmar |
| --- | --- | --- |
| 1. `test: characterize finance authorization and model lifecycle` | Pruebas de permisos y callbacks actuales; registrar en esta especificación la matriz comprobada y diferencias respecto a supuestos iniciales | Suites de caracterización y registro de fallos preexistentes |
| 2. `refactor: move integrated finance app into monorepo` | Traslado y ajustes de ejecución, build y Docker; actualizar `AGENTS.md`, enlaces frontend, `overview.md`, `local-development.md` y `deployment.md` con las rutas efectivas | Pruebas de aplicación, type-check, pruebas Vue, build, scripts operativos y validación Compose |
| 3. `refactor: extract shared finance models and dependencies` | Paquete, consumidores, factories, hooks locales y compatibilidad; actualizar `boundaries.md`, instalación Composer y procedimiento de compatibilidad/rollback | Pruebas de modelos, host independiente, factories, autenticación, eventos, morphs y colas |
| 4. `refactor: centralize finance authorization and visibility rules` | Reglas compartidas, policies y consultas consumidoras; actualizar límites de autorización y referencias de dominio afectadas | Matriz de acceso, aislamiento de listados/agregados, privacidad y regresiones de escritura |
| 5. `test: verify shared package isolation and monorepo workflows` | Cobertura de integración restante y ajustes necesarios; actualizar comandos verificados y limitaciones operativas documentadas | Validación integrada completa, instalación limpia y ensayo local de operación/rollback |
| 6. `docs: finalize monorepo migration verification` | Conciliar esta especificación con el resultado, revisar enlaces y retirar instrucciones obsoletas restantes | Correspondencia entre documentación, scripts y resultados reales; diff limpio |

Cada commit debe ser coherente: el traslado incorpora sus ajustes operativos; la extracción de modelos incorpora todos sus consumidores y compatibilidad; la extracción de permisos incorpora las llamadas desde Finance. No separar cambios que dejen imports rotos entre commits.

Los mensajes serán convencionales y en inglés. Si una etapa requiere varios commits, dividir por una capacidad completa y comprobable, manteniendo sus pruebas y documentación juntas. No dejar las pruebas de seguridad para el commit 5 ni las instrucciones de uso para el commit 6. No incluir cambios ajenos ni reescribir commits existentes para ocultar correcciones.

Antes de cada commit: revisar `git diff` y archivos preparados, aplicar las skills pertinentes, comprobar tipos/imports/nombres, ejecutar las validaciones de la etapa y `git diff --check`, y confirmar que no se incorporan secretos, dependencias generadas o datos locales. Resolver las regresiones introducidas antes de avanzar. Un fallo preexistente o una herramienta no disponible debe quedar identificado, sin presentar la comprobación como superada.

## Skills e instrucciones durante la implementación

Antes de cada etapa, consultar `AGENTS.md` y las instrucciones de directorio aplicables. Para frontend, conservar y consultar `apps/finance/resources/js/AGENTS.md` después del traslado. Utilizar la documentación vigente en `docs`, especialmente arquitectura, límites, cuentas compartidas, dinero personal y operación; `docs/history/workspace` es referencia histórica y no sustituye esas reglas.

Leer las skills correspondientes antes de aplicarlas y comunicar su uso la primera vez. No asumir su contenido solo por el nombre ni aplicar todas indiscriminadamente:

| Skill | Aplicación en este plan |
| --- | --- |
| `development-plan` e `implementation-plan` | Mantener alcance, decisiones, dependencias y validación de esta especificación alineados con los hallazgos |
| `php-laravel` | Extracción del paquete, modelos, policies, providers, adaptación de consumidores e inyección de dependencias |
| `test-design` | Caracterización de permisos, límites de integración, invariantes financieras y cobertura del paquete |
| `docker-compose-traefik` | Cambios de contexto, mounts, redes, configuración y ejecución local de servicios existentes |
| `markdown-plantuml-docs` | Actualizar documentación técnica existente en español; agregar diagramas solo si aclaran una decisión |
| `vue3` | Cuando sea necesario modificar código Vue/TypeScript; respetar módulos y contratos actuales |
| `api-change` | Al adaptar la aplicación de autorización en endpoints, preservar contratos y semántica de errores |
| `database-change` | Al modificar comportamiento de consultas, mapeos persistidos o compatibilidad morph; no implica crear migraciones |
| `debugging-playbook` | Investigar regresiones con evidencia antes de cambiar código |

Si una necesidad real exige alterar componentes PrimeVue, estilos o migraciones, leer también las skills específicas antes de hacerlo; esas modificaciones no forman parte del alcance inicial. No crear nuevas Actions compartidas ni ampliar la tarea como consecuencia de una recomendación genérica de una skill.

Aplicar los lineamientos de `AGENTS.md`: código en inglés, tipos explícitos, casts conservados, autorización y validación de escrituras, inyección de dependencias, early returns, DTOs cuando representen una responsabilidad clara y pruebas para cada service class nueva o modificada. Conservar las invariantes de ledger, custodia, reembolsos y privacidad personal. Mantener trabajo y validaciones en local.

## Documentación incremental

La documentación forma parte de cada etapa y se actualiza en el mismo commit que cambia el comportamiento o la operación descritos. Editar los documentos vigentes en `docs` y las instrucciones afectadas; no generar documentación paralela que compita con ellos.

- Antes de modificar una capacidad, leer su referencia vigente y comprobar sus invariantes contra el código.
- Al cambiar rutas o comandos, actualizar de inmediato las guías de desarrollo, despliegue e instrucciones que los mencionan.
- Al extraer modelos o permisos, actualizar los límites de responsabilidad y mapas de implementación afectados; las reglas de producto permanecen intactas.
- Mantener en esta especificación el estado de las etapas, decisiones ajustadas y resultados de validación con sus limitaciones. Registrar solo comprobaciones realmente ejecutadas, sin copiar logs ni secretos.
- Revisar enlaces y rutas después de cada movimiento. Mantener documentos nuevos o modificados en español y conservar el historial documental sin reescribirlo.
- El cierre revisa consistencia y pendientes; no es el momento de documentar por primera vez cambios ya realizados.

## Archivos estimados a modificar

- Raíz: `AGENTS.md`, `.gitignore`, Compose, `deploy.sh`, `docker/nginx/*`, `docker/php/*` y metadatos de herramientas con rutas.
- Trasladados: `app`, `bootstrap`, `config`, `database`, `public`, `resources`, `routes`, `storage`, `tests`, `scripts`, `artisan`, `helpers.php`, manifiestos/locks y configuración PHP/JS/TS.
- Finance: `composer.json`, `composer.lock`, `config/auth.php`, providers, middleware JWT, imports de clases y enums, controladores/requests/servicios con permisos, factories y pruebas.
- Paquete: manifiesto, provider, modelos, enums/traits necesarios, reglas, policies, consultas de visibilidad y pruebas independientes.
- Documentación vigente: `docs/architecture/overview.md`, `boundaries.md`, `docs/operations/local-development.md`, `deployment.md`, instrucciones frontend y referencias activas afectadas.

## Riesgos

La extracción es transversal: el principal riesgo funcional es confundir la política nominal con el permiso real, o proteger detalles y olvidar agregados. Los principales riesgos operativos son perder acceso a paquetes mediante rutas Docker, seleccionar otro volumen MySQL y romper payloads serializados por el cambio de namespace.

Mover todos los modelos incluye entidades sensibles y aumenta la superficie del paquete; no equivale a conceder permisos sobre todas ellas. Conservar pruebas de privacidad, acceso interno explícito y límites de serialización. El paquete es reutilizable dentro de Laravel; no se pretende independencia del framework.

## Preguntas abiertas

No hay una decisión de producto que bloquee comenzar con este alcance. Se adoptan `apps/finance`, `packages/finance-domain` y `FinSi\FinanceDomain` como nombres iniciales.

Antes de una futura operación de despliegue deberán identificarse el nombre efectivo de Compose, el volumen existente, los directorios persistentes y los tipos de payload pendientes. Son comprobaciones del entorno de destino; este plan no autoriza conexiones remotas ni supone sus valores.

## Plan de implementación

| Etapa | Propósito y cambios | Dependencia | Validación y resultado |
| --- | --- | --- | --- |
| 1. Línea base y matriz | Inventariar todos los modelos, relaciones, callbacks, permisos por endpoint, factories, morphs y colas; añadir caracterización en las suites existentes | Ninguna | Pruebas actuales registradas; reglas y compatibilidad identificadas antes de mover código |
| 2. Traslado funcional | Mover archivos versionados a `apps/finance`; ajustar configuración, ignores, scripts, Compose y procedimiento de transición | 1 | Laravel, Vue, assets, pruebas y Compose funcionan en la ruta nueva sin cambios de dominio |
| 3. Paquete de modelos | Crear Composer/provider; mover modelos y dependencias; desacoplar Actions/eventos/auth; actualizar consumidores, factories y nombres persistidos | 2 | Paquete carga sin Finance; modelos conservan relaciones/casts; escrituras y eventos de Finance conservan comportamiento |
| 4. Autorización completa | Extraer reglas y restricciones de lectura, mantener permisos de escritura; conectar policies y todos los consumidores con actor explícito | 3 | Matriz de permisos cubierta; consultas, detalles, agregados y rutas anidadas conservan aislamiento y respuestas |
| 5. Validación integrada | Ejecutar suites, host mínimo del paquete, comprobaciones de arquitectura, build y ensayo local de operación/rollback | 4 | Sin regresiones nuevas; sin dependencias inversas; sin cambios de esquema ni servicios adicionales |
| 6. Cierre | Verificar la documentación actualizada durante cada etapa, conciliar el plan con lo implementado, revisar diff y completar commits convencionales | 5 | Monorepo utilizable con Finance consumiendo exclusivamente los modelos y permisos compartidos |

### Criterio de aceptación

La implementación termina cuando Finance funciona desde su nueva ubicación, no mantiene copias de reglas compartidas, todos los modelos viven en el paquete, las Actions siguen locales, los permisos de lectura pueden ejecutarse sin contexto HTTP y las verificaciones anteriores pasan. La documentación vigente refleja cada cambio completado, las skills aplicables se han utilizado y los cambios están registrados en commits convencionales coherentes. No existen aplicaciones MCP/agentes ni infraestructura nueva para ellas.

## Seguimiento de implementación

- Etapas 1–4 implementadas: aplicación trasladada, modelos/enums/traits extraídos, hooks locales, compatibilidad de nombres, policies y restricciones compartidas conectadas a Finance. No se crearon MCP ni agentes.
- Composer instala el paquete por copia; `domain:sync` actualiza desarrollo y el despliegue lo reinstala explícitamente incluso si la versión/lock no cambia. `.gitattributes` evita copiar el vendor de pruebas del paquete.
- La caracterización se registró en `d7823c2`. El traslado y la extracción se agrupan en un commit funcional porque sus cambios de autoload, consumidores y ejecución deben quedar coherentes; el cierre y verificaciones se registran después.
- Se corrigió el arranque de comandos Telegram para resolver sus dependencias al ejecutar el comando, permitiendo instalar Finance sin credenciales de esa integración. Se agregó una prueba de consola.
- Se conservó el comportamiento de permisos por endpoint, incluida la lectura global de transacciones con cuenta archivada y la exclusión de cuentas archivadas en listas/adjuntos.
- Se actualizó el cálculo del correo antiguo para invocar la Action local fuera de Blade, evitando depender del método retirado del modelo.
- Revisión independiente sin regresiones críticas confirmadas; corregidas referencias Blade a enums antiguos y argumentos redundantes.
- PHP: 359 pruebas aprobadas y una omitida en la última suite completa; las pruebas enfocadas posteriores de consola y ciclo de vida pasan (8 pruebas, 23 aserciones). Paquete independiente: 14 pruebas, 183 aserciones.
- Vue: type-check y build pasan; 263 pruebas pasan y 2 fallan en `TransactionsForm.spec.ts`, exactamente los mismos fallos obtenidos antes de modificar código.
- MySQL 8.0 aislado: 23 pruebas pasan y una falla por comparación estricta de `pivot.percentage` (`"65.00"` frente a `65`). Reproducido también en `d7823c2` mediante checkout temporal: no es una regresión de esta migración.
- Formatter: se corrigió una opción inválida preexistente y se deshabilitó la conversión a closures estáticas incompatible con Pest. El paquete y archivos nuevos se formatean de forma enfocada; el chequeo general detecta cientos de diferencias de estilo heredadas, que no se corrigen masivamente en este cambio.
- Navegador: verificación de SPA con assets compilados y rutas profundas aprobada en host. La validación de contenedores y el cierre documental continúan.
