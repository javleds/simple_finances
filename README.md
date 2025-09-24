# Simple Finances

Una aplicación de finanzas personales desarrollada con Laravel y Filament, con integración de Telegram Bot y OpenAI para el procesamiento automático de transacciones.

## Características

- **Panel de Administración**: Interfaz intuitiva desarrollada con FilamentPHP
- **Bot de Telegram**: Procesamiento de transacciones mediante texto, imágenes y audio
- **Integración OpenAI**: Extracción automática de información de transacciones usando IA
- **Gestión de Cuentas**: Múltiples cuentas bancarias y de efectivo
- **Metas Financieras**: Seguimiento de objetivos de ahorro
- **Transacciones**: Registro de ingresos y gastos con categorización

## Requisitos del Sistema

- PHP 8.1 o superior
- Composer
- SQLite (por defecto) o MySQL/PostgreSQL
- Node.js y NPM (para compilar assets)
- Docker (opcional, para desarrollo)

## Variables de Entorno

Asegúrate de configurar las siguientes variables en tu archivo `.env`:

```env
# Telegram Bot
TELEGRAM_BOT_TOKEN=tu_token_de_telegram
TELEGRAM_WEBHOOK_URL=https://tu-dominio.com/telegram/webhook

# OpenAI (Opcional - Si no se configura, se usa modo dummy)
OPENAI_API_TOKEN=tu_token_de_openai

# Base de datos (SQLite por defecto)
DB_CONNECTION=sqlite
```

### Variables de OpenAI (Opcionales)

```env
OPENAI_DEFAULT_MODEL=gpt-4o-mini
OPENAI_VISION_MODEL=gpt-4o
OPENAI_AUDIO_MODEL=whisper-1
OPENAI_MAX_TOKENS=1000
OPENAI_TEMPERATURE=0.3
```

## Instalación

1. Clona el repositorio:
```bash
git clone https://github.com/javleds/simple_finances.git
cd simple_finances
```

2. Instala las dependencias de PHP:
```bash
composer install
```

3. Instala las dependencias de Node.js:
```bash
npm install
```

4. Copia el archivo de configuración:
```bash
cp .env.example .env
```

5. Genera la clave de aplicación:
```bash
php artisan key:generate
```

6. Ejecuta las migraciones:
```bash
php artisan migrate --seed
```

7. Compila los assets:
```bash
npm run build
```

8. Inicia el servidor de desarrollo:
```bash
php artisan serve
```

## Bot de Telegram

El bot de Telegram puede procesar transacciones de las siguientes formas:

### Procesamiento de Texto
```
"Deposité 1500 pesos en mi cuenta de ahorros para mi fondo de emergencia"
"Gasté 250 en supermercado con mi tarjeta de débito"
```

### Procesamiento de Imágenes
- Envía una foto de un recibo, ticket o comprobante
- El bot usará OCR para extraer la información de la transacción

### Procesamiento de Audio
- Envía un mensaje de voz describiendo la transacción
- El bot transcribirá el audio y procesará la información

### Campos Requeridos
Para crear una transacción exitosamente, se requiere:
- **Cuenta**: Nombre de la cuenta (debe existir en el sistema)
- **Monto**: Cantidad de la transacción
- **Tipo**: "ingreso" o "gasto"

### Campos Opcionales
- **Fecha**: Si no se especifica, se usa la fecha actual
- **Meta Financiera**: Solo para ingresos, si se menciona una meta específica

## Comandos del Bot

- `/start` - Iniciar el proceso de verificación
- `/verify [código]` - Verificar cuenta con código de 6 dígitos

## Configuración del Bot de Telegram

1. Crea un bot con [@BotFather](https://t.me/botfather)
2. Obtén el token y configúralo en `TELEGRAM_BOT_TOKEN`
3. Configura el webhook:
```bash
php artisan telegram:set-webhook https://tu-dominio.com
```

## Integración con OpenAI

La aplicación puede funcionar con o sin OpenAI:

- **Con OpenAI**: Procesamiento inteligente de texto, imágenes y audio
- **Sin OpenAI**: Modo dummy que simula respuestas (para desarrollo/testing)

### Costos de OpenAI
- **Texto**: ~$0.0001 por mensaje
- **Imágenes**: ~$0.01 por imagen
- **Audio**: ~$0.006 por minuto

## Desarrollo

### Ejecutar tests
```bash
php artisan test
```

### Linting de código
```bash
./vendor/bin/pint
```

### Compilar assets en modo desarrollo
```bash
npm run dev
```

### Docker (Opcional)
```bash
docker-compose up -d
```

## Arquitectura

El proyecto sigue los principios de Clean Architecture:

- **Contracts**: Interfaces para servicios
- **DTOs**: Objetos de transferencia de datos
- **Services**: Lógica de negocio
- **Processors**: Procesadores específicos de Telegram
- **Models**: Eloquent models para la base de datos

## Contribuir

1. Fork el proyecto
2. Crea una rama para tu feature (`git checkout -b feature/nueva-caracteristica`)
3. Commit tus cambios (`git commit -am 'Agregar nueva característica'`)
4. Push a la rama (`git push origin feature/nueva-caracteristica`)
5. Crea un Pull Request

## Licencia

Este proyecto está licenciado bajo la [MIT License](https://opensource.org/licenses/MIT).
