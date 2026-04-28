<script setup lang="ts">
import AppButton from '@/modules/shared/components/AppButton.vue';
import AppCard from '@/modules/shared/components/AppCard.vue';
import AppText from '@/modules/shared/components/AppText.vue';
import AppTitle from '@/modules/shared/components/AppTitle.vue';

const userItems = [
  {
    title: 'Hugo Díaz',
    detail: 'Administrador de cuenta',
    meta: 'Acceso total',
  },
  {
    title: 'Ana Ruiz',
    detail: 'Responsable de conciliación',
    meta: 'Lectura y movimientos',
  },
  {
    title: 'Luis Pérez',
    detail: 'Aprobador secundario',
    meta: 'Permisos limitados',
  },
];
</script>

<template>
  <section class="space-y-4">
    <AppCard class="rounded-3xl">
      <div class="flex items-center justify-between gap-3">
        <div class="space-y-1">
          <AppTitle as="h2" size="sm">Usuarios</AppTitle>
          <AppText>Gestión embebida de miembros, roles y permisos de la cuenta.</AppText>
        </div>
        <AppButton variant="outline">Agregar usuario</AppButton>
      </div>
    </AppCard>

    <AppCard v-for="user in userItems" :key="user.title" class="rounded-3xl">
      <div class="flex items-start justify-between gap-4">
        <div class="space-y-1">
          <p class="text-sm font-semibold text-[var(--app-color-text)]">{{ user.title }}</p>
          <AppText size="sm">{{ user.detail }}</AppText>
        </div>
        <AppText size="sm" tone="subtle">{{ user.meta }}</AppText>
      </div>
    </AppCard>
  </section>
</template>
