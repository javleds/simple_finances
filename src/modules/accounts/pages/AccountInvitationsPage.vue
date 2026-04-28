<script setup lang="ts">
import AppButton from '@/modules/shared/components/AppButton.vue';
import AppCard from '@/modules/shared/components/AppCard.vue';
import AppText from '@/modules/shared/components/AppText.vue';
import AppTitle from '@/modules/shared/components/AppTitle.vue';

const invitationItems = [
  {
    title: 'Carlos Mendoza',
    detail: 'Pendiente de aceptar invitación como aprobador.',
    meta: 'Expira en 3 días',
  },
  {
    title: 'María Torres',
    detail: 'Invitación enviada para seguimiento financiero.',
    meta: 'Reenviada ayer',
  },
];
</script>

<template>
  <section class="space-y-4">
    <AppCard class="rounded-3xl">
      <div class="flex items-center justify-between gap-3">
        <div class="space-y-1">
          <AppTitle as="h2" size="sm">Invitaciones</AppTitle>
          <AppText>Invita usuarios a colaborar dentro de esta cuenta.</AppText>
        </div>
        <AppButton variant="outline">Nueva invitación</AppButton>
      </div>
    </AppCard>

    <AppCard v-for="invitation in invitationItems" :key="invitation.title" class="rounded-3xl">
      <div class="space-y-1">
        <p class="text-sm font-semibold text-[var(--app-color-text)]">{{ invitation.title }}</p>
        <AppText size="sm">{{ invitation.detail }}</AppText>
        <AppText size="sm" tone="subtle">{{ invitation.meta }}</AppText>
      </div>
    </AppCard>
  </section>
</template>
