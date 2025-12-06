# Lineamientos para el proyecto
- Proyecto en Laravel Filament 3; todo el codigo generado debe estar en ingles.
- No agregar comentarios en el codigo ni documentacion extra salvo que se pida de forma explicita.
- Seguir principios SOLID con enfoque en clases de servicio nombradas como acciones y, de ser posible, con un unico metodo publico.
- Comunicar Filament con las service classes mediante DTOs.
- Usar inyeccion de dependencias siempre que se pueda; si no es viable, usar el helper `app(Class::class)`.
- Evitar if/else anidados y, en general, la sentencia `else`; preferir early returns.
- Sustituir switch/case por un patron Strategy con registro automatico en el contenedor de Laravel para manejar la logica por clases.
- Crear pruebas automatizadas para cada service class.
- Todos los documentos .md generados por codex CLI deben estar en español a menos que se indique lo contrario.
- Por defualt, los modelos de laravel en filament son unguarded, porque esta logina la maneja filament, la propiedad $fillable no es requerida, pero si genera los casts usando la funcion protected function casts(): array correctos dependiendo del tipo. 
- Genera codigo completamente tipado, en funciones, returnos, argumentos, etc.
- Usa short sytax disponible en las últimas versiones de PHP compatibles con este proyecto (PHP 8.2). 

## Contexto funcional
- Aplicacion para finanzas personales: usuarios crean cuentas con transacciones de ingreso y egreso; el balance se calcula al registrar movimientos.
- Cuentas de tipo credito emulan tarjetas de credito; feature incompleto y requiere manejo especifico.
- Subscripciones: registrar servicios (Netflix, YouTube Premium, etc.), periodo y monto; generar proyecciones mensuales/anuales y sugerir ahorro mensual o quincenal para pagos anuales.
- Cuentas compartidas entre multiples usuarios: cada usuario puede editar/eliminar solo sus transacciones; otros usuarios las ven y reciben notificaciones por email en cada movimiento.
- Notificacion semanal: cada domingo se envia email con transacciones en CSV segun configuracion de notificaciones (por transaccion, por cuenta y por movimientos).
- Eloquent usa un scope global para filtrar por el usuario activo; todos los modelos tienen `user_id` salvo relaciones many-to-many y cuentas compartidas que se resuelven por joins.
- Orquestacion sobre eventos: clases de orquestacion (ej. `CreateTransaction`) llaman servicios para crear transaccion, recalcular balance y enviar notificacion sin contener logica de dominio; preferir este enfoque a eventos.
