# Plan de Acción: Extensión de Procesadores de Telegram

## Objetivo
Extender el sistema de procesadores de mensajes de Telegram para soportar consultas de balance, historial de movimientos, y operaciones de modificación/eliminación de transacciones de forma escalable.

## Arquitectura Propuesta
Implementar un sistema de acciones escalable similar al sistema actual de procesadores, donde OpenAI determinará primero la acción a realizar antes de procesarla.

---

## ✅ Fase 1: Diseño de la Arquitectura de Acciones

### 1.1 Crear Enumeración de Acciones de Telegram
- [x] Crear nuevo enum `MessageAction` para clasificar tipos de mensajes de usuario
- [x] Incluir acciones: `CreateTransaction`, `QueryBalance`, `QueryRecentTransactions`, `ModifyLastTransaction`, `DeleteLastTransaction`
- [x] Mantener separación clara con el enum `Action` existente (que maneja eventos de transacciones)

### 1.2 Crear Contratos e Interfaces
- [x] Crear `MessageActionProcessorInterface` para definir el contrato de procesadores de acciones
- [x] Crear `MessageActionDetectionServiceInterface` para el servicio de detección de acciones

### 1.3 Crear DTOs para las Nuevas Acciones
- [x] Crear `MessageActionDetectionDto` para encapsular la respuesta de detección de acción
- [x] Crear `BalanceQueryDto` para consultas de balance
- [x] Crear `RecentTransactionsQueryDto` para consultas de movimientos recientes
- [x] Crear `TransactionModificationDto` para modificaciones de transacciones

---

## ✅ Fase 2: Implementación del Sistema de Detección de Acciones

### 2.1 Crear Servicio de Detección de Acciones con OpenAI
- [x] Crear `MessageActionDetectionService` que use OpenAI para determinar la acción del usuario
- [x] Implementar prompt especializado para clasificar mensajes según el enum `MessageAction`
- [x] Crear `DummyMessageActionDetectionService` para desarrollo/testing

### 2.2 Crear Factory de Procesadores de Acciones
- [x] Crear `MessageActionProcessorFactory` similar al `TelegramMessageProcessorFactory`
- [x] Implementar registro automático de procesadores de acciones de Telegram
- [x] Añadir sistema de prioridades para procesadores de acciones

### 2.3 Modificar OpenAI Service
- [x] Extender `OpenAIServiceInterface` con método `detectMessageAction(string $text): array`
- [x] Implementar método en `OpenAIService` y `DummyOpenAIService`
- [x] Crear prompt para detección de acciones en `MessageActionDetectionPrompt`

---

## ✅ Fase 3: Implementación de Procesadores de Acciones

### 3.1 Crear Procesador de Consulta de Balance
- [ ] Crear `BalanceQueryActionProcessor` que implemente `MessageActionProcessorInterface`
- [ ] Implementar lógica para obtener balance de cuenta por nombre
- [ ] Formatear respuesta con balance actual y información adicional

### 3.2 Crear Procesador de Consulta de Movimientos Recientes
- [ ] Crear `RecentTransactionsActionProcessor`
- [ ] Implementar consulta de últimos 5 movimientos de una cuenta específica
- [ ] Incluir: concepto, tipo, fecha, nombre de usuario
- [ ] Formatear respuesta legible para Telegram

### 3.3 Crear Procesador de Modificación de Transacciones
- [ ] Crear `ModifyLastTransactionActionProcessor`
- [ ] Implementar búsqueda de última transacción creada por el usuario
- [ ] Validar permisos de modificación
- [ ] Procesar nueva información de transacción con OpenAI
- [ ] Actualizar transacción y disparar eventos correspondientes

### 3.4 Crear Procesador de Eliminación de Transacciones
- [ ] Crear `DeleteLastTransactionActionProcessor`
- [ ] Implementar búsqueda de última transacción creada por el usuario
- [ ] Validar permisos de eliminación
- [ ] Eliminar transacción y disparar eventos correspondientes
- [ ] Proporcionar confirmación de eliminación

---

## ✅ Fase 4: Integración con el Sistema Existente

### 4.1 Modificar TextMessageProcessor
- [ ] Integrar detección de acciones antes del procesamiento de transacciones
- [ ] Añadir lógica para decidir entre procesar como transacción o como acción
- [ ] Mantener compatibilidad con el flujo actual

### 4.2 Crear Servicio Coordinador
- [ ] Crear `MessageActionProcessingService` para coordinar el procesamiento de acciones
- [ ] Integrar con el sistema existente de procesamiento de mensajes
- [ ] Manejar errores y casos edge de forma consistente

### 4.3 Registro Automático de Procesadores de Acciones
- [ ] Crear `MessageActionProcessorServiceProvider` para registro automático
- [ ] Escanear directorio `Services/Telegram/Actions` para procesadores
- [ ] Implementar sistema de registro similar al actual

---

## ✅ Fase 5: Servicios de Apoyo y Utilidades

### 5.1 Crear Servicios de Consulta
- [ ] Crear `AccountBalanceService` para consultas de balance optimizadas
- [ ] Crear `TransactionHistoryService` para consultas de historial
- [ ] Implementar caché si es necesario para optimización

### 5.2 Crear Servicios de Modificación
- [ ] Crear `LastTransactionService` para operaciones sobre última transacción del usuario
- [ ] Implementar validaciones de permisos y propietario
- [ ] Asegurar integridad de datos en modificaciones/eliminaciones

### 5.3 Helpers y Utilidades
- [ ] Crear `MessageActionHelper` para formateo de respuestas de acciones
- [ ] Añadir métodos de utilidad para formateo de balances y movimientos
- [ ] Crear mensajes de respuesta consistentes y en español

---

## ✅ Fase 6: Testing y Validación

### 6.1 Tests Unitarios
- [ ] Tests para cada procesador de acción
- [ ] Tests para el servicio de detección de acciones
- [ ] Tests para la factory de procesadores de acciones

### 6.2 Tests de Integración
- [ ] Tests de integración con el sistema de procesamiento existente
- [ ] Validar flujo completo desde mensaje hasta respuesta
- [ ] Tests con usuarios y cuentas reales

### 6.3 Testing Manual
- [ ] Probar cada acción a través de Telegram
- [ ] Validar mensajes de error y casos edge
- [ ] Verificar que no se rompe funcionalidad existente

---

## ✅ Fase 7: Documentación y Finalización

### 7.1 Actualizar Documentación
- [ ] Actualizar README con las nuevas funcionalidades
- [ ] Documentar comandos y ejemplos de uso
- [ ] Añadir ejemplos de cada tipo de acción

### 7.2 Optimizaciones Finales
- [ ] Revisar performance de consultas de base de datos
- [ ] Optimizar prompts de OpenAI para mejor precisión
- [ ] Ajustar mensajes de respuesta basado en feedback

---

## Estructura de Archivos a Crear

```
app/
├── Contracts/
│   ├── MessageActionProcessorInterface.php
│   └── MessageActionDetectionServiceInterface.php
├── Enums/
│   └── MessageAction.php
├── Services/
│   ├── Telegram/
│   │   ├── Actions/
│   │   │   ├── BalanceQueryActionProcessor.php
│   │   │   ├── RecentTransactionsActionProcessor.php
│   │   │   ├── ModifyLastTransactionActionProcessor.php
│   │   │   └── DeleteLastTransactionActionProcessor.php
│   │   ├── MessageActionProcessorFactory.php
│   │   ├── MessageActionProcessingService.php
│   │   └── Helpers/
│   │       └── MessageActionHelper.php
│   ├── OpenAI/
│   │   ├── MessageActionDetectionService.php
│   │   ├── DummyMessageActionDetectionService.php
│   │   └── Prompts/
│   │       └── MessageActionDetectionPrompt.php
│   ├── Transaction/
│   │   └── LastTransactionService.php
│   └── Account/
│       ├── AccountBalanceService.php
│       └── TransactionHistoryService.php
├── Dto/
│   ├── MessageActionDetectionDto.php
│   ├── BalanceQueryDto.php
│   ├── RecentTransactionsQueryDto.php
│   └── TransactionModificationDto.php
└── Providers/
    └── MessageActionProcessorServiceProvider.php
```

## Consideraciones Técnicas

- **Separación de Responsabilidades**: El nuevo enum `MessageAction` se enfoca en clasificar intenciones de usuario, mientras que el enum `Action` existente maneja eventos del sistema
- **Escalabilidad**: El sistema debe permitir agregar nuevas acciones de Telegram fácilmente
- **Compatibilidad**: No debe romper la funcionalidad existente de creación de transacciones
- **Performance**: Las consultas deben ser optimizadas para no impactar rendimiento
- **Seguridad**: Validar permisos para modificación/eliminación de transacciones
- **UX**: Mensajes claros y informativos en español
- **Logging**: Registrar todas las acciones para debugging y auditoría

## Estimación de Tiempo
- **Fase 1-2**: 2-3 días
- **Fase 3**: 3-4 días  
- **Fase 4-5**: 2-3 días
- **Fase 6-7**: 1-2 días

**Total estimado**: 8-12 días de desarrollo
