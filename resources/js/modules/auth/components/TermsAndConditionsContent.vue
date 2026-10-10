<script setup lang="ts">
import { AppText, AppTitle } from '@/modules/shared/components';

withDefaults(defineProps<{ embedded?: boolean }>(), { embedded: false });
</script>

<template>
    <div class="space-y-5">
        <div class="space-y-2">
            <AppTitle :as="embedded ? 'h2' : 'h1'" size="sm">Términos y condiciones</AppTitle>
            <AppText>
                Estos términos regulan el acceso y uso de la plataforma de registro y autenticación.
            </AppText>
        </div>

        <section class="space-y-2">
            <AppTitle :as="embedded ? 'h3' : 'h2'" size="sm">1. Uso de la cuenta</AppTitle>
            <AppText>
                Debe proporcionar información verdadera, actualizada y suficiente para crear su
                cuenta.
            </AppText>
            <AppText>
                Usted es responsable de mantener la confidencialidad de sus credenciales y del uso
                de su sesión.
            </AppText>
        </section>

        <section class="space-y-2">
            <AppTitle :as="embedded ? 'h3' : 'h2'" size="sm">2. Conducta permitida</AppTitle>
            <AppText>
                No puede utilizar la plataforma para actividades ilícitas, fraudulentas o que
                afecten a otros usuarios o al servicio.
            </AppText>
        </section>

        <section class="space-y-2">
            <AppTitle :as="embedded ? 'h3' : 'h2'" size="sm">3. Disponibilidad</AppTitle>
            <AppText>
                El servicio puede cambiar, suspenderse o recibir mantenimiento sin previo aviso
                cuando sea necesario para operación, seguridad o mejora continua.
            </AppText>
        </section>
    </div>
</template>
