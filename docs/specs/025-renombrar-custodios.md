# Renombrar custodios virtuales

## Contexto
Los miembros ya pueden crear y retirar custodios desde Usuarios, pero no corregir sus nombres.

## Objetivo
Permitir renombrar un custodio activo de la cuenta sin efectos financieros.

## Alcance
Acción Renombrar custodio en el menú actual, modal con nombre precargado y actualización del nombre visible.

## Fuera de alcance
Cambios a miembros reales, custodia, saldos, reparto, deudas e identidades de acceso.

## Comportamiento esperado
Cualquier miembro vigente puede guardar un nombre de 1 a 100 caracteres sin espacios exteriores. Cancelar no guarda. Guardar bloquea envíos duplicados. Los errores mantienen el modal abierto.

## Decisiones técnicas
Reutilizar AccountCustodianModal, AppModal, AppInput, AppActionMenu y usePrimeForm con Zod. Extender useAccountCustodians y su repositorio. Acción Laravel RenameAccountCustodian con bloqueo de cuenta y custodio igual al retiro para serializar ambas operaciones.

## Componentes afectados
Controlador, request, acción, rutas y pruebas de custodios; menú, lista, página, modal, composable y repositorio Vue.

## Backend
Autorizar membresía antes de ejecutar la acción; buscar exclusivamente custodio activo perteneciente a la cuenta. Actualizar únicamente name.

## Frontend
Modo renombrar en el modal existente sin campos financieros; invalidar las consultas actuales de cuentas, dashboard, movimientos y dinero personal para refrescar etiquetas.

## Persistencia
Reutilizar users.name. Sin migración ni reescritura de descripciones históricas. Conservar identificadores y ledger.

## API
PUT /api/accounts/{account}/custodians/{custodian}, cuerpo {name: string}. Respuesta 200 con data del custodio; 403 para no miembros, 404 para custodio ajeno, real o retirado, 422 para nombre inválido. Operación idempotente.

## Validaciones
Nombre requerido, string, máximo 100; recorte por middleware y formulario. Solo asignación explícita del nombre.

## Permisos y seguridad
Todos los miembros vigentes; ninguna ampliación de permisos sobre usuarios reales. Las identidades virtuales no autentican.

## Casos límite
Mismo nombre permitido; nombres duplicados conservan el comportamiento de creación. Deudas pendientes no impiden renombrar. Retiro concurrente se serializa mediante bloqueo de cuenta.

## Estrategia de pruebas
API: éxito por miembro no propietario, invariancia financiera, aislamiento de cuentas, rechazo de usuarios reales/retirados/no miembros y nombres inválidos. Vue: precarga, emisión del nombre recortado, cancelación, errores y bloqueo durante guardado.

## Estrategia de validación
Pruebas PHP de custodios, Vitest relevante, type-check y build. Revisión de diff y formato.

## Estrategia de commits
Un commit funcional feat(accounts): allow renaming virtual custodians con pruebas y documentación.

## Archivos estimados a modificar
AccountCustodianController, RenameAccountCustodianRequest, RenameAccountCustodian, routes/api.php, AccountCustodianTest, AccountUserListItem, AccountUsersList, AccountUsersPage, AccountCustodianModal, useAccountCustodians, accountsRepository y sus pruebas.

## Riesgos
Etiquetas cacheadas: invalidar consultas existentes. No cambiar descripciones históricas para evitar reescribir movimientos.

## Preguntas abiertas
Ninguna.

## Plan de implementación
1. Añadir request y acción acotada al custodio activo, endpoint y pruebas de permisos e invariancia.
2. Conectar Renombrar desde el menú al modo de formulario existente y repositorio; probar validación y emisión.
3. Ejecutar las suites relevantes, type-check y build; revisar cambios y crear commit.
