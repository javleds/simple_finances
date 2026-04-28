<script setup lang="ts">
import { Cog6ToothIcon, ShieldCheckIcon } from '@heroicons/vue/24/outline';

import SettingsSectionCard from '@/modules/settings/components/SettingsSectionCard.vue';
import { AppCard, AppText, AppTitle } from '@/modules/shared/components';

const sections = [
  {
    title: 'Usuarios y permisos',
    description: 'Administra roles, accesos y responsables operativos.',
  },
  {
    title: 'Notificaciones',
    description: 'Configura avisos de cargos, cortes y eventos relevantes.',
  },
  {
    title: 'Seguridad',
    description: 'Revisa políticas de acceso y sesiones activas.',
  },
];
</script>

<template>
  <div class="space-y-5">
    <AppCard class="rounded-3xl">
      <div class="flex items-start gap-4">
        <div
          class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-[color-mix(in_srgb,var(--app-color-primary)_12%,transparent)] text-[var(--app-color-primary)]"
        >
          <Cog6ToothIcon class="h-6 w-6" />
        </div>

        <div class="space-y-2">
          <AppText size="sm" tone="subtle">Configuración general</AppText>
          <AppTitle as="h2" size="sm">Preferencias de la facility</AppTitle>
          <AppText>
            Centraliza ajustes operativos, seguridad y experiencia del equipo.
          </AppText>
        </div>
      </div>

      <div
        class="mt-5 flex items-center gap-3 rounded-2xl border bg-[var(--app-color-surface-muted)] px-4 py-3"
        :style="{ borderColor: 'var(--app-color-border)' }"
      >
        <ShieldCheckIcon class="h-5 w-5 shrink-0 text-emerald-600" />
        <AppText size="sm">La autenticación reforzada está habilitada para administradores.</AppText>
      </div>
    </AppCard>

    <div class="space-y-1">
      <AppTitle as="h2" size="sm">Secciones</AppTitle>
      <AppText>Accesos rápidos a la configuración disponible en esta versión.</AppText>
    </div>

    <section class="space-y-4">
      <SettingsSectionCard
        v-for="section in sections"
        :key="section.title"
        :title="section.title"
        :description="section.description"
      />
    </section>
  </div>
</template>
