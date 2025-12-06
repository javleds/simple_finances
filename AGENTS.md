# Lineamientos para el proyecto
- Proyecto en Laravel Filament 3; todo el codigo generado debe estar en ingles.
- No agregar comentarios en el codigo ni documentacion extra salvo que se pida de forma explicita.
- Seguir principios SOLID con enfoque en clases de servicio nombradas como acciones y, de ser posible, con un unico metodo publico.
- Comunicar Filament con las service classes mediante DTOs.
- Usar inyeccion de dependencias siempre que se pueda; si no es viable, usar el helper `app(Class::class)`.
- Evitar if/else anidados y, en general, la sentencia `else`; preferir early returns.
- Sustituir switch/case por un patron Strategy con registro automatico en el contenedor de Laravel para manejar la logica por clases.
- Crear pruebas automatizadas para cada service class.
