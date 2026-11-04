<?php

declare(strict_types=1);

namespace App\Services\Legal;

use function config;

use const FILTER_VALIDATE_EMAIL;
use const FILTER_VALIDATE_URL;

use function filter_var;
use function hash;
use function in_array;
use function json_encode;

use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

use function trim;

class GetLegalDocuments
{
    public function execute(): array
    {
        $provider = [];
        foreach (['name', 'rfc', 'address', 'email', 'phone', 'site_url', 'brand'] as $key) {
            $provider[$key] = trim((string) config('legal.provider.' . $key, ''));
        }
        $available = ! in_array('', $provider, true)
            && '' !== trim((string) config('legal.terms_version'))
            && '' !== trim((string) config('legal.privacy_version'))
            && false !== filter_var($provider['email'] ?? '', FILTER_VALIDATE_EMAIL)
            && false !== filter_var($provider['site_url'] ?? '', FILTER_VALIDATE_URL);
        $terms = $this->document('Términos y condiciones', (string) config('legal.terms_version'), $provider, $available ? $this->terms() : []);
        $privacy = $this->document('Aviso de privacidad', (string) config('legal.privacy_version'), $provider, $available ? $this->privacy($provider) : []);

        return ['available' => $available, 'provider' => $provider, 'documents' => ['terms' => $terms, 'privacy' => $privacy]];
    }

    private function document(string $title, string $version, array $provider, array $sections): array
    {
        $document = ['title' => $title, 'version' => $version, 'sections' => $sections];
        $document['hash'] = hash('sha256', json_encode(['provider' => $provider, 'document' => $document], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

        return $document;
    }

    private function terms(): array
    {
        return [
            ['title' => 'El servicio y su titular', 'paragraphs' => ['Fin-si es una herramienta para registrar y organizar finanzas personales y cuentas compartidas. El titular, domicilio, RFC y medios de contacto se identifican al inicio de estos documentos. Fin-si no es una institución financiera, no custodia ni mueve fondos y sus registros no sustituyen estados de cuenta, asesoría profesional ni comprobantes fiscales.']],
            ['title' => 'Edad y aceptación', 'paragraphs' => ['El servicio está dirigido exclusivamente a personas de 18 años o más. Para registrarte y continuar operando debes declarar que eres mayor de edad y aceptar expresamente estos términos y el aviso de privacidad vigente. Conservamos evidencia de la versión y el contenido aceptados.']],
            ['title' => 'Tu cuenta y tus registros', 'paragraphs' => ['Debes proporcionar información veraz, proteger tus credenciales y comunicar accesos no autorizados al contacto del titular. Eres responsable de la exactitud y legalidad de los registros y archivos que incorporas. No cargues datos de terceros sin contar con una base legítima para hacerlo.', 'Las cuentas compartidas muestran movimientos, participaciones y obligaciones a sus integrantes según sus permisos. Al compartir una cuenta, considera que otros integrantes podrán conocer la información financiera correspondiente. Los permisos de edición y eliminación dependen de las reglas de la aplicación.']],
            ['title' => 'Funciones e integraciones opcionales', 'paragraphs' => ['Puedes registrar ingresos, gastos, cuentas y suscripciones a servicios externos. Este registro de suscripciones no significa que Fin-si cobre una suscripción propia.', 'Telegram, WhatsApp y el procesamiento asistido por OpenAI son opcionales y dependen de su configuración y disponibilidad. Revisa el aviso de privacidad antes de vincularlos o enviar contenido. Verifica cualquier propuesta automática antes de basar decisiones en ella: puede contener errores.']],
            ['title' => 'Uso permitido y disponibilidad', 'paragraphs' => ['No uses el servicio para actividades ilícitas, vulnerar la seguridad, suplantar identidades o acceder a información ajena. El acceso podrá restringirse ante abusos o incidentes de seguridad, con atención por el canal de soporte.', 'Trabajamos para mantener el servicio disponible y proteger los datos, pero puede haber mantenimiento, fallos o interrupciones. Conserva respaldos de la información que necesites y verifica los saldos con sus fuentes originales. Estas condiciones no excluyen derechos irrenunciables ni responsabilidades que establezca la ley.']],
            ['title' => 'Cambios, contacto y cierre', 'paragraphs' => ['Los cambios a estos documentos se publican con una versión identificable. Cuando cambie el contenido sujeto a aceptación, se solicitará nuevamente antes de continuar operando.', 'Puedes solicitar soporte o el cierre de tu cuenta por el correo indicado. La cancelación y conservación de información se evalúan conforme al aviso de privacidad, los derechos de otras personas y las obligaciones legales aplicables. Estas condiciones se rigen por las leyes mexicanas, sin limitar tus derechos ante las autoridades competentes.']],
        ];
    }

    private function privacy(array $provider): array
    {
        $email = $provider['email'];

        return [
            ['title' => 'Responsable de los datos personales', 'paragraphs' => ["{$provider['name']}, persona física que opera bajo la marca {$provider['brand']}, es responsable del tratamiento de los datos personales en Fin-si. RFC: {$provider['rfc']}. Domicilio: {$provider['address']}. Sitio: {$provider['site_url']}. Contacto para privacidad y soporte: {$email}. Teléfono: {$provider['phone']}."]],
            ['title' => 'Datos que tratamos', 'paragraphs' => ['Tratamos tu nombre, correo electrónico, contraseña protegida mediante hash y teléfono si lo proporcionas; datos de verificación y vinculación de mensajería; y los datos financieros y patrimoniales que registras, como cuentas, saldos, ingresos, egresos, obligaciones compartidas y suscripciones externas.', 'También tratamos archivos adjuntos y, si eliges enviarlos por una integración disponible, mensajes, imágenes y audios; así como información técnica necesaria para seguridad, funcionamiento y atención de incidencias. No solicitamos datos sensibles como salud o biométricos. Evita incluirlos en registros o archivos.']],
            ['title' => 'Finalidades necesarias y consentimiento expreso', 'paragraphs' => ['Usamos los datos para crear y proteger tu cuenta, organizar tus finanzas, calcular balances y participaciones, gestionar invitaciones y cuentas compartidas, enviar avisos operativos configurados, atender soporte y solicitudes de derechos, prevenir abusos y cumplir obligaciones legales.', 'Al marcar la aceptación del aviso, otorgas tu consentimiento expreso para tratar los datos financieros y patrimoniales que aportas con estas finalidades. Puedes revocarlo y solicitar la cancelación por correo; esto puede impedir prestar las funciones que dependen de esos datos. No utilizamos tus datos para publicidad, mercadotecnia, análisis de comportamiento ni para autorizar entrenamiento de modelos de inteligencia artificial.']],
            ['title' => 'Cuentas compartidas y proveedores', 'paragraphs' => ['Los integrantes de una cuenta compartida pueden consultar los datos financieros de esa cuenta conforme a sus permisos. Las notificaciones por correo y resúmenes configurados pueden contener movimientos y archivos CSV; configura sus destinatarios y protege tu correo.', 'Los proveedores de infraestructura, correo y almacenamiento tratan los datos necesarios para operar el servicio bajo las condiciones aplicables. El tratamiento puede realizarse fuera de México. No vendemos datos personales. Las comunicaciones exigidas por ley se atienden conforme a los requisitos legales.']],
            ['title' => 'Telegram, WhatsApp y OpenAI', 'paragraphs' => ['Estas integraciones son opcionales. Al vincular o utilizar Telegram o WhatsApp, sus plataformas reciben los identificadores y el contenido que envías y aplican sus propios avisos de privacidad. La vinculación puede requerir tu teléfono, identificador de chat y códigos de verificación.', 'Cuando utilices funciones asistidas disponibles en Telegram, el contenido necesario de mensajes, imágenes o audios podrá enviarse a OpenAI para transcripción, interpretación y preparación de registros financieros. Evita enviar contenido innecesario o datos de otras personas. No autorizamos su uso para entrenar modelos. Desvincular una integración detiene su uso futuro en Fin-si; no elimina automáticamente el historial conservado por la plataforma externa.']],
            ['title' => 'Conservación y seguridad', 'paragraphs' => ['Aplicamos controles de acceso, contraseñas protegidas y medidas técnicas y organizativas para reducir accesos indebidos. Ningún sistema elimina todo riesgo. Conservamos los datos mientras sean necesarios para prestar el servicio y atender responsabilidades legales, seguridad o controversias; después procede su eliminación, bloqueo o anonimización según corresponda.', 'La cancelación se atiende manualmente. Los registros que afecten derechos de otros integrantes, evidencia de aceptación y respaldos sujetos a ciclos de conservación pueden requerir tratamiento diferenciado. No prometemos una eliminación inmediata ni un plazo uniforme para todos los datos.']],
            ['title' => 'Derechos ARCO y revocación', 'paragraphs' => ["Puedes solicitar acceso, rectificación, cancelación u oposición (ARCO), revocar tu consentimiento o limitar el uso de tus datos escribiendo a {$email}. Indica tu nombre, medio para responder, los datos o cuenta involucrados y una descripción clara de tu solicitud. Para rectificación, indica la corrección y su respaldo.", 'Te indicaremos un medio proporcional para acreditar tu identidad o representación y, si hace falta, información adicional. No envíes documentos de identidad por canales públicos. Atenderemos la solicitud y comunicaremos su procedencia y ejecución dentro de los plazos legales aplicables, explicando cualquier restricción legal. Puedes acudir a la autoridad competente si consideras vulnerados tus derechos.']],
            ['title' => 'Tecnologías y cambios al aviso', 'paragraphs' => ['El sitio utiliza almacenamiento local y tecnologías necesarias para mantener tu sesión y preferencias operativas. No utilizamos tecnologías de seguimiento publicitario.', 'Publicamos los cambios en esta página con su versión. Cuando el contenido aceptado cambie, pediremos una nueva aceptación dentro de la aplicación. Este aviso y su aceptación se conservan con fecha, versión y una copia de los documentos presentados.']],
        ];
    }
}
