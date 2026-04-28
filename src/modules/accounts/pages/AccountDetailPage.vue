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

import { type AccountRecord, findAccountById } from '@/modules/accounts/data/accounts';
import { AppButton, AppCard, AppLink, AppModal, AppText, AppTitle } from '@/modules/shared/components';

type AccountRelationSection = 'transactions' | 'invitations' | 'goals' | 'users';

const route = useRoute();
const isDeleteModalOpen = ref(false);
const activeSection = ref<AccountRelationSection>('transactions');

const account = computed(() => {
  const accountId = typeof route.params.accountId === 'string' ? route.params.accountId : '';
  return findAccountById(accountId);
});

const relationshipSections = [
  {
    value: 'transactions',
    label: 'Transacciones',
    icon: ArrowsRightLeftIcon,
  },
  {
    value: 'invitations',
    label: 'Invitaciones',
    icon: EnvelopeIcon,
  },
  {
    value: 'goals',
    label: 'Metas',
    icon: FlagIcon,
  },
  {
    value: 'users',
    label: 'Usuarios',
    icon: UsersIcon,
  },
] as const;

const transactionItems = [
  {
    title: 'Pago a proveedor logístico',
    amount: '-$12,480',
    meta: 'Hoy · Conciliada',
  },
  {
    title: 'Dispersión interna desde facility',
    amount: '+$35,000',
    meta: 'Ayer · Entrada',
  },
  {
    title: 'Consumo operativo regional',
    amount: '-$4,860',
    meta: '22 Abr · Pendiente de revisión',
  },
];

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

const goalItems = [
  {
    title: 'Ahorro de reserva trimestral',
    detail: 'Avance de 68% sobre la meta objetivo.',
    meta: '$68,000 de $100,000',
    progress: 68,
  },
  {
    title: 'Fondo operativo mensual',
    detail: 'Objetivo para cubrir gastos recurrentes del siguiente ciclo.',
    meta: '$24,500 de $30,000',
    progress: 82,
  },
];

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

function sectionButtonClasses(section: AccountRelationSection): string {
  if (activeSection.value === section) {
    return 'bg-[var(--app-color-primary)] text-[var(--app-color-primary-foreground)]';
  }

  return 'bg-[var(--app-color-surface-muted)] text-[var(--app-color-text-subtle)] hover:text-[var(--app-color-text)]';
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
      <AppText>
        El bloque superior permanece fijo conceptualmente y esta navegación cambia solo las vistas
        embebidas de la cuenta.
      </AppText>
    </div>

    <AppCard class="rounded-3xl !p-3">
      <div class="flex gap-2 overflow-x-auto pb-1">
        <button
          v-for="section in relationshipSections"
          :key="section.value"
          type="button"
          class="inline-flex shrink-0 items-center gap-2 rounded-full px-4 py-2 text-sm font-semibold transition focus:outline-none focus:ring-4 focus:ring-[var(--app-color-focus-ring)]"
          :class="sectionButtonClasses(section.value)"
          @click="activeSection = section.value"
        >
          <component :is="section.icon" class="h-4 w-4" />
          <span>{{ section.label }}</span>
        </button>
      </div>
    </AppCard>

    <section v-if="activeSection === 'transactions'" class="space-y-4">
      <AppCard class="rounded-3xl">
        <div class="space-y-1">
          <AppTitle as="h2" size="sm">Transacciones</AppTitle>
          <AppText>Vista embebida para revisar actividad y conciliación de la cuenta.</AppText>
        </div>
      </AppCard>

      <AppCard
        v-for="transaction in transactionItems"
        :key="transaction.title"
        class="rounded-3xl"
      >
        <div class="flex items-start justify-between gap-4">
          <div class="space-y-1">
            <p class="text-sm font-semibold text-[var(--app-color-text)]">{{ transaction.title }}</p>
            <AppText size="sm">{{ transaction.meta }}</AppText>
          </div>
          <p class="shrink-0 text-sm font-semibold tabular-nums text-[var(--app-color-text)]">
            {{ transaction.amount }}
          </p>
        </div>
      </AppCard>
    </section>

    <section v-else-if="activeSection === 'invitations'" class="space-y-4">
      <AppCard class="rounded-3xl">
        <div class="flex items-center justify-between gap-3">
          <div class="space-y-1">
            <AppTitle as="h2" size="sm">Invitaciones</AppTitle>
            <AppText>Invita usuarios a colaborar dentro de esta cuenta.</AppText>
          </div>
          <AppButton variant="outline">Nueva invitación</AppButton>
        </div>
      </AppCard>

      <AppCard
        v-for="invitation in invitationItems"
        :key="invitation.title"
        class="rounded-3xl"
      >
        <div class="space-y-1">
          <p class="text-sm font-semibold text-[var(--app-color-text)]">{{ invitation.title }}</p>
          <AppText size="sm">{{ invitation.detail }}</AppText>
          <AppText size="sm" tone="subtle">{{ invitation.meta }}</AppText>
        </div>
      </AppCard>
    </section>

    <section v-else-if="activeSection === 'goals'" class="space-y-4">
      <AppCard class="rounded-3xl">
        <div class="flex items-center justify-between gap-3">
          <div class="space-y-1">
            <AppTitle as="h2" size="sm">Metas financieras</AppTitle>
            <AppText>Cada meta vive dentro de la cuenta y comparte su mismo contexto.</AppText>
          </div>
          <AppButton variant="outline">Crear meta</AppButton>
        </div>
      </AppCard>

      <AppCard v-for="goal in goalItems" :key="goal.title" class="rounded-3xl">
        <div class="space-y-3">
          <div class="space-y-1">
            <p class="text-sm font-semibold text-[var(--app-color-text)]">{{ goal.title }}</p>
            <AppText size="sm">{{ goal.detail }}</AppText>
          </div>

          <div class="space-y-2">
            <div class="flex items-center justify-between gap-3">
              <AppText size="sm" tone="subtle">Progreso</AppText>
              <p class="text-sm font-semibold text-[var(--app-color-text)]">{{ goal.meta }}</p>
            </div>
            <div class="h-2 rounded-full bg-[var(--app-color-surface-muted)]">
              <div
                class="h-2 rounded-full bg-[var(--app-color-primary)]"
                :style="{ width: `${goal.progress}%` }"
              />
            </div>
          </div>
        </div>
      </AppCard>
    </section>

    <section v-else class="space-y-4">
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
