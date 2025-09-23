# Plan de Acción: Integración de OpenAI para Procesamiento de Transacciones

## Preparación
- [x] Crea una rama de github nueva a partr de la rama actual antes de ejecutar las siguientes tareas.

## 1. Configuración Base de OpenAI

### 1.1 Configuración de Entorno
- [x] Agregar `OPENAI_API_TOKEN` en archivo `.env` y `.env.example`
- [ ] Documentar en README.md las nuevas variables de ambiente requeridas
- [x] Agregar configuración de OpenAI en `config/services.php`

### 1.2 Dependencias
- [x] Instalar el SDK oficial de OpenAI para PHP via Composer: `composer require openai-php/client`
- [x] Instalar cliente HTTP requerido: `composer require guzzlehttp/guzzle` (si no está instalado)

## 2. Contratos y DTOs

### 2.1 Interface Principal de OpenAI
- [x] Crear `app/Contracts/OpenAIServiceInterface.php` con métodos:
  - `processText(string $text): array`
  - `processImage(string $imagePath): array` 
  - `processAudio(string $audioPath): array`

### 2.2 DTOs para Datos Estructurados
- [x] Crear `app/Dto/TransactionExtractionDto.php` con propiedades:
  - `account` (required)
  - `amount` (required) 
  - `type` (required: income|outcome)
  - `date` (optional, default today)
  - `financial_goal` (optional, default null)
- [x] Crear `app/Dto/OpenAIRequestDto.php` para requests a OpenAI
- [x] Crear `app/Dto/OpenAIResponseDto.php` para responses de OpenAI

### 2.3 Enums Adicionales
- [x] Verificar/extender `app/Enums/TransactionType.php` para asegurar compatibilidad con `income|outcome`

## 3. Implementaciones del Servicio OpenAI

### 3.1 Implementación Real
- [x] Crear `app/Services/OpenAI/OpenAIService.php` que implemente `OpenAIServiceInterface`
- [x] Implementar método `processText()` usando el endpoint de chat completions
- [x] Implementar método `processImage()` usando vision model para OCR
- [x] Implementar método `processAudio()` usando Whisper API para transcripción
- [x] Agregar manejo de errores y logging
- [x] Implementar retry logic para requests fallidos

### 3.2 Implementación Dummy
- [x] Crear `app/Services/OpenAI/DummyOpenAIService.php` que implemente `OpenAIServiceInterface`
- [x] Implementar métodos que retornen respuestas mock/vacías
- [x] Agregar logging para debugging cuando se use el servicio dummy

### 3.3 Factory/Provider Pattern
- [x] Crear `app/Providers/OpenAIServiceProvider.php` para registrar la implementación correcta
- [x] Configurar binding condicional basado en `OPENAI_API_TOKEN`
- [x] Registrar el provider en `config/app.php`
- [ ] Agregar logging para debugging cuando se use el servicio dummy

### 3.3 Factory/Provider Pattern
- [ ] Crear `app/Providers/OpenAIServiceProvider.php` para registrar la implementación correcta
- [ ] Configurar binding condicional basado en `OPENAI_API_TOKEN`
- [ ] Registrar el provider en `config/app.php`

## 4. Procesador de Transacciones con IA

### 4.1 Servicio de Procesamiento de Transacciones
- [x] Crear `app/Services/Transaction/TransactionProcessorService.php`
- [x] Inyectar `OpenAIServiceInterface` y repositorios necesarios
- [x] Implementar lógica de validación de campos requeridos
- [x] Implementar lógica de creación de transacciones
- [x] Agregar manejo de errores específicos (campos faltantes, cuenta no encontrada, etc.)

### 4.2 Validación y Mapeo de Datos
- [x] Crear `app/Services/Transaction/TransactionDataValidator.php`
- [x] Implementar validación de cuentas del usuario
- [x] Implementar validación de metas financieras del usuario
- [x] Implementar parseo/validación de fechas
- [x] Implementar validación de montos (números válidos)

## 5. Actualización de Procesadores de Telegram

### 5.1 Modificar TextMessageProcessor
- [x] Actualizar `app/Services/Telegram/Processors/TextMessageProcessor.php`
- [x] Inyectar `TransactionProcessorService` 
- [x] Implementar lógica de llamada a OpenAI para texto
- [x] Manejar respuestas exitosas y errores
- [x] Mantener backward compatibility para otros tipos de mensajes de texto

### 5.2 Modificar PhotoMessageProcessor  
- [x] Actualizar `app/Services/Telegram/Processors/PhotoMessageProcessor.php`
- [x] Inyectar `TransactionProcessorService`
- [x] Implementar descarga temporal de imagen
- [x] Implementar lógica de llamada a OpenAI para OCR
- [x] Limpiar archivos temporales después del procesamiento

### 5.3 Modificar VoiceMessageProcessor
- [x] Actualizar `app/Services/Telegram/Processors/VoiceMessageProcessor.php` 
- [x] Inyectar `TransactionProcessorService`
- [x] Implementar descarga temporal de audio
- [x] Implementar lógica de llamada a OpenAI para transcripción
- [x] Limpiar archivos temporales después del procesamiento

### 5.4 Modificar PhotoWithCaptionMessageProcessor
- [x] Actualizar `app/Services/Telegram/Processors/PhotoWithCaptionMessageProcessor.php`
- [x] Combinar procesamiento de imagen + texto del caption
- [x] Priorizar texto del caption sobre OCR si ambos están disponibles

## 6. Prompts y Configuración de IA

### 6.1 Prompts del Sistema
- [x] Crear `app/Services/OpenAI/Prompts/TransactionExtractionPrompt.php`
- [x] Definir prompt del sistema para extracción de transacciones
- [x] Incluir ejemplos de entrada y salida esperada
- [x] Especificar formato JSON de respuesta requerido
- [x] Configurar instrucciones para manejar casos ambiguos

### 6.2 Configuración de Modelos
- [x] Configurar modelo para texto (ej: gpt-4o-mini)
- [x] Configurar modelo para visión (ej: gpt-4o)
- [x] Configurar modelo para audio (whisper-1)
- [x] Definir parámetros de temperatura y max_tokens

## 7. Manejo de Errores y Mensajes

### 7.1 Mensajes de Error en Español
- [ ] Crear mensajes cuando falten campos obligatorios
- [ ] Crear mensajes para cuentas no encontradas
- [ ] Crear mensajes para errores de OpenAI API
- [ ] Crear mensajes para formatos de datos inválidos
- [ ] Crear mensajes de confirmación para transacciones creadas exitosamente

### 7.2 Logging y Monitoreo
- [ ] Implementar logging detallado para requests a OpenAI
- [ ] Implementar logging para transacciones creadas automáticamente
- [ ] Agregar métricas de uso de API
- [ ] Implementar alertas para errores recurrentes

## 8. Helpers y Utilidades

### 8.1 Helper para Procesamiento de Archivos
- [x] Crear `app/Services/Telegram/Helpers/MediaProcessorHelper.php`
- [x] Implementar métodos para descarga temporal de archivos
- [x] Implementar validación de tipos de archivo
- [x] Implementar límites de tamaño de archivo
- [x] Implementar limpieza automática de archivos temporales

### 8.2 Helper para Mapeo de Datos
- [x] Crear `app/Services/Transaction/Helpers/TransactionMappingHelper.php`
- [x] Implementar mapeo de nombres de cuenta a IDs
- [x] Implementar mapeo de nombres de metas financieras a IDs
- [x] Implementar normalización de tipos de transacción
- [x] Implementar parseo inteligente de fechas en español

## 9. Testing y Validación

### 9.1 Tests Unitarios (Opcional según instrucciones)
- [ ] Tests para `OpenAIService` con mocks
- [ ] Tests para `DummyOpenAIService`
- [ ] Tests para `TransactionProcessorService`
- [ ] Tests para procesadores de Telegram actualizados

### 9.2 Testing Manual
- [ ] Verificar funcionamiento con `OPENAI_API_TOKEN` configurado
- [ ] Verificar funcionamiento con `OPENAI_API_TOKEN` no configurado (dummy)
- [ ] Probar diferentes tipos de mensajes de texto
- [ ] Probar procesamiento de imágenes con texto
- [ ] Probar procesamiento de mensajes de voz
- [ ] Validar manejo de errores y casos edge

## 10. Documentación y Configuración

### 10.1 Documentación de Usuario
- [ ] Documentar nuevas capacidades del bot en README.md
- [ ] Crear ejemplos de mensajes que el bot puede procesar
- [ ] Documentar formato esperado para diferentes tipos de transacciones
- [ ] Documentar limitaciones y casos no soportados

### 10.2 Configuración de Deployment
- [ ] Actualizar archivos de configuración para producción
- [ ] Documentar variables de ambiente requeridas
- [ ] Configurar rate limiting para API de OpenAI
- [ ] Configurar monitoring y alertas

## 11. Optimizaciones y Mejoras Futuras

### 11.1 Performance
- [ ] Implementar cache para respuestas similares
- [ ] Optimizar tamaño de prompts para reducir costos
- [ ] Implementar batch processing para múltiples requests

### 11.2 Funcionalidades Avanzadas  
- [ ] Soporte para múltiples idiomas
- [ ] Aprendizaje de patrones de usuario
- [ ] Integración con calendario para fechas futuras
- [ ] Soporte para transacciones recurrentes

---

## Consideraciones Técnicas Importantes

### Seguridad
- [ ] Validar que el usuario tenga acceso a las cuentas mencionadas
- [ ] Implementar rate limiting por usuario
- [ ] Sanitizar datos antes de enviar a OpenAI
- [ ] No logear información sensible

### Costos y Límites
- [ ] Implementar límites de uso por usuario/día
- [ ] Monitorear costos de API de OpenAI
- [ ] Implementar fallbacks para cuando se excedan límites

### Escalabilidad
- [ ] Considerar uso de colas para procesamiento asíncrono
- [ ] Implementar circuit breakers para APIs externas
- [ ] Planificar para múltiples instancias del servicio

---

*Este plan está diseñado para ser implementado de forma incremental, permitiendo testing y validación en cada fase antes de proceder a la siguiente.*
