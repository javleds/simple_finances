<script setup lang="ts">
import {
  PencilSquareIcon,
  TrashIcon,
  ArrowsRightLeftIcon,
  EnvelopeIcon,
  FlagIcon,
  UsersIcon,
} from '@heroicons/vue/24/outline';
import { computed, ref } from 'vue';
import { useRoute } from 'vue-router';

import AccountActionCard from '@/modules/accounts/components/AccountActionCard.vue';
import { type AccountRecord, findAccountById } from '@/modules/accounts/data/accounts';
import { AppButton, AppCard, AppLink, AppModal, AppText, AppTitle } from '@/modules/shared/components';

const route = useRoute();
const isDeleteModalOpen = ref(false);

const account = computed(() => {
  const accountId = typeof route.params.accountId === 'string' ? route.params.accountId : '';
  return findAccountById(accountId);
});

const relationshipActions = [
  {
    title: 'Transacciones',
    description: 'Consulta movimientos, consumos y conciliaciones de la cuenta.',
    icon: ArrowsRightLeftIcon,
  },
  {
    title: 'Invitaciones',
    description: 'Invita usuarios a colaborar y define su nivel de acceso.',
    icon: EnvelopeIcon,
  },
  {
    title: 'Metas financieras',
    description: 'Crea objetivos y sigue su avance con base en el balance.',
    icon: FlagIcon,
  },
  {
    title: 'Usuarios',
    description: 'Gestiona responsables, aprobadores y miembros activos.',
    icon: UsersIcon,
  },
];

function openDeleteModal(): void {
  isDeleteModalOpen.value = true;
}

function closeDeleteModal(): void {
  isDeleteModalOpen.value = false;
}

function statusClasses(status: AccountRecord['status']): string {
  if (status === 'Activo') {
    return 'bg-emerald-500/12 text-emerald-600';
  }

  return 'bg-slate-500/12 text-slate-600 dark:text-slate-300';
}
</script>

<template>
  <div v-if="account" class="space-y-5">
    <div class="flex items-center justify-between gap-3">
      <AppLink :to="{ name: 'admin.accounts' }" variant="subtle">Volver a cuentas</AppLink>
      <AppText size="sm" tone="subtle">Detalle de cuenta</AppText>
    </div>

    <AppCard class="overflow-hidden !p-0">
      <div class="relative px-5 py-6 sm:px-6">
        <div
          class="absolute inset-x-0 top-0 h-28 opacity-95"
          :style="{
            background: `linear-gradient(135deg, ${account.color}, color-mix(in srgb, ${account.color} 55%, white))`,
          }"
        />

        <div class="relative space-y-4">
          <div class="flex items-start justify-between gap-4">
            <div class="space-y-2">
              <div class="flex items-center gap-3">
                <span class="h-3 w-3 rounded-[4px]" :style="{ backgroundColor: account.color }" />
                <span
                  class="inline-flex items-center rounded-full px-2.5 py-1 text-[10px] font-semibold uppercase tracking-[0.04em]"
                  :class="statusClasses(account.status)"
                >
                  {{ account.status }}
                </span>
              </div>

              <AppTitle as="h2" size="sm" class="!text-white">
                {{ account.name }}
              </AppTitle>
            </div>

            <div
              class="rounded-2xl border border-white/20 bg-white/10 px-3 py-2 text-right backdrop-blur"
            >
              <p class="text-xs font-medium text-white/75">Balance</p>
              <p class="text-sm font-semibold text-white">{{ account.balance }}</p>
            </div>
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div class="rounded-3xl border border-white/15 bg-white/12 p-4">
              <p class="text-sm text-white/75">Línea de crédito</p>
              <p class="mt-1 text-2xl font-semibold text-white">{{ account.creditLine }}</p>
            </div>
            <div class="rounded-3xl border border-white/15 bg-white/12 p-4">
              <p class="text-sm text-white/75">Crédito disponible</p>
              <p class="mt-1 text-2xl font-semibold text-white">{{ account.availableCredit }}</p>
            </div>
          </div>
        </div>
      </div>
    </AppCard>

    <div class="grid grid-cols-2 gap-3">
      <AppButton variant="outline">
        <PencilSquareIcon class="mr-2 h-4 w-4" />
        Editar
      </AppButton>
      <AppButton variant="outline" @click="openDeleteModal">
        <TrashIcon class="mr-2 h-4 w-4" />
        Eliminar
      </AppButton>
    </div>

    <AppCard class="rounded-3xl">
      <div class="space-y-4">
        <div class="space-y-1">
          <AppTitle as="h2" size="sm">Información importante</AppTitle>
          <AppText>Campos prioritarios para operación y seguimiento de esta cuenta.</AppText>
        </div>

        <div class="grid grid-cols-2 gap-3">
          <div
            class="rounded-2xl border bg-[var(--app-color-surface-muted)] px-4 py-3"
            :style="{ borderColor: 'var(--app-color-border)' }"
          >
            <AppText size="sm" tone="subtle">Total gastado</AppText>
            <p class="mt-1 text-lg font-semibold text-[var(--app-color-text)]">
              {{ account.totalSpent }}
            </p>
          </div>

          <div
            class="rounded-2xl border bg-[var(--app-color-surface-muted)] px-4 py-3"
            :style="{ borderColor: 'var(--app-color-border)' }"
          >
            <AppText size="sm" tone="subtle">Cuenta de alimentación</AppText>
            <p class="mt-1 text-lg font-semibold text-[var(--app-color-text)]">
              {{ account.fundingAccount }}
            </p>
          </div>
        </div>
      </div>
    </AppCard>

    <div class="space-y-1">
      <AppTitle as="h2" size="sm">Relaciones de la cuenta</AppTitle>
      <AppText>Desde aquí se administran las superficies que normalmente viven en el detalle.</AppText>
    </div>

    <section class="space-y-4">
      <AccountActionCard
        v-for="action in relationshipActions"
        :key="action.title"
        :title="action.title"
        :description="action.description"
        :icon="action.icon"
      />
    </section>

    <AppModal :open="isDeleteModalOpen" title="Eliminar cuenta" close-label="Cancelar" @close="closeDeleteModal">
      <div class="space-y-4">
        <AppText>
          Vas a eliminar <strong>{{ account.name }}</strong>. Esta acción debe confirmar dependencias,
          usuarios y metas financieras antes de ejecutarse.
        </AppText>

        <div class="flex justify-end">
          <AppButton type="button" variant="primary" @click="closeDeleteModal">
            Confirmar eliminación
          </AppButton>
        </div>
      </div>
    </AppModal>
  </div>

  <AppCard v-else class="rounded-3xl">
    <div class="space-y-3">
      <AppTitle as="h2" size="sm">Cuenta no encontrada</AppTitle>
      <AppText>La cuenta solicitada no existe o ya no está disponible en esta facility.</AppText>
      <AppLink :to="{ name: 'admin.accounts' }" variant="primary">Volver a la lista</AppLink>
    </div>
  </AppCard>
</template>
