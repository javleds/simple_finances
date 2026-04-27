<script setup lang="ts">
import { AppText, AppTitle } from '@/modules/shared/components';
</script>

<template>
  <div class="space-y-5">
    <div class="space-y-2">
      <AppTitle as="h1" size="sm">Política de privacidad</AppTitle>
      <AppText>
        Esta política describe cómo recopilamos, usamos y protegemos la información compartida al
        registrarse.
      </AppText>
    </div>

    <section class="space-y-2">
      <AppTitle as="h2" size="sm">1. Datos recopilados</AppTitle>
      <AppText>
        Podemos almacenar su nombre, correo electrónico y datos técnicos mínimos necesarios para la
        operación segura de la cuenta.
      </AppText>
    </section>

    <section class="space-y-2">
      <AppTitle as="h2" size="sm">2. Uso de la información</AppTitle>
      <AppText>
        Utilizamos los datos para crear su cuenta, autenticar su acceso, comunicarnos con usted y
        mejorar la experiencia del servicio.
      </AppText>
    </section>

    <section class="space-y-2">
      <AppTitle as="h2" size="sm">3. Protección y derechos</AppTitle>
      <AppText>
        Aplicamos medidas razonables de seguridad y usted puede solicitar actualización o revisión
        de sus datos conforme a la normativa aplicable.
      </AppText>
    </section>
  </div>
</template>
