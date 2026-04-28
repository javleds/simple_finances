<script setup lang="ts">
import {
  ArrowsRightLeftIcon,
  EnvelopeIcon,
  FlagIcon,
  InformationCircleIcon,
  PencilSquareIcon,
  TrashIcon,
  UsersIcon,
} from '@heroicons/vue/24/outline';
import { computed, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';

import { type AccountRecord, findAccountById } from '@/modules/accounts/data/accounts';
import {
  AppButton,
  AppCard,
  AppContextTabs,
  AppLink,
  AppModal,
  AppText,
  AppTitle,
} from '@/modules/shared/components';

type AccountRelationSection = 'details' | 'transactions' | 'invitations' | 'goals' | 'users';

const route = useRoute();
const router = useRouter();
const isDeleteModalOpen = ref(false);

const account = computed(() => {
  const accountId = typeof route.params.accountId === 'string' ? route.params.accountId : '';
  return findAccountById(accountId);
});

const relationshipSections = [
  {
    value: 'details',
    label: 'Detalles',
    icon: InformationCircleIcon,
  },
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

const validSections = relationshipSections.map((section) => section.value);

const activeSection = computed<AccountRelationSection>(() => {
  const section = route.params.section;

  if (typeof section !== 'string') {
    return 'transactions';
  }

  if (validSections.includes(section as AccountRelationSection)) {
    return section as AccountRelationSection;
  }

  return 'transactions';
});

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

const usersPendingSummary = computed(() => {
  if (!account.value) {
    return 0;
  }

  return account.value.users.filter((user) => user.pendingExpenses !== '$0.00').length;
});

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

function updateActiveSection(nextSection: string): void {
  const accountId = typeof route.params.accountId === 'string' ? route.params.accountId : '';

  if (!accountId) {
    return;
  }

  router.push({
    name: 'admin.accounts.detail',
    params: {
      accountId,
      section: nextSection,
    },
  });
}
</script>

<template>
  <div v-if="account" class="space-y-5 pb-16">
    <section v-if="activeSection === 'details'" class="space-y-5">
      <AppCard class="rounded-3xl">
        <div class="space-y-4">
          <div class="flex items-start justify-between gap-4">
            <div class="min-w-0 space-y-2">
              <div class="flex items-center gap-3">
                <span class="h-3 w-3 rounded-[4px]" :style="{ backgroundColor: account.color }" />
                <span
                  class="inline-flex items-center rounded-full px-2.5 py-1 text-[10px] font-semibold uppercase tracking-[0.04em]"
                  :class="statusClasses(account.status)"
                >
                  {{ account.status }}
                </span>
              </div>

              <AppTitle as="h2" size="sm">{{ account.name }}</AppTitle>
            </div>

            <div class="shrink-0 text-right">
              <AppText size="sm" tone="subtle">Balance</AppText>
              <p class="mt-1 text-2xl font-semibold tracking-tight text-[var(--app-color-text)]">
                {{ account.balance }}
              </p>
            </div>
          </div>

          <div v-if="account.accountType === 'credito'" class="grid grid-cols-2 gap-3">
            <div
              class="rounded-2xl border bg-[var(--app-color-surface-muted)] px-4 py-3"
              :style="{ borderColor: 'var(--app-color-border)' }"
            >
              <AppText size="sm" tone="subtle">Línea de crédito</AppText>
              <p class="mt-1 text-lg font-semibold text-[var(--app-color-text)]">
                {{ account.creditLine }}
              </p>
            </div>

            <div
              class="rounded-2xl border bg-[var(--app-color-surface-muted)] px-4 py-3"
              :style="{ borderColor: 'var(--app-color-border)' }"
            >
              <AppText size="sm" tone="subtle">Crédito disponible</AppText>
              <p class="mt-1 text-lg font-semibold text-[var(--app-color-text)]">
                {{ account.availableCredit }}
              </p>
            </div>
          </div>
        </div>
      </AppCard>

      <AppCard class="rounded-3xl">
        <div class="space-y-5">
          <div class="space-y-2">
            <AppTitle as="h2" size="sm">Detalle de cuenta</AppTitle>
            <AppText>{{ account.description }}</AppText>
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div
              class="rounded-2xl border bg-[var(--app-color-surface-muted)] px-4 py-3"
              :style="{ borderColor: 'var(--app-color-border)' }"
            >
              <AppText size="sm" tone="subtle">Color</AppText>
              <div class="mt-2 flex items-center gap-3">
                <span
                  class="h-4 w-4 rounded-[4px] border border-white/30"
                  :style="{ backgroundColor: account.color }"
                />
                <p class="text-sm font-semibold text-[var(--app-color-text)]">
                  {{ account.color }}
                </p>
              </div>
            </div>

            <div
              class="rounded-2xl border bg-[var(--app-color-surface-muted)] px-4 py-3"
              :style="{ borderColor: 'var(--app-color-border)' }"
            >
              <AppText size="sm" tone="subtle">Tipo de cuenta</AppText>
              <p class="mt-2 text-sm font-semibold text-[var(--app-color-text)]">
                {{ account.accountType === 'credito' ? 'Crédito' : 'Débito' }}
              </p>
            </div>

            <div
              class="rounded-2xl border bg-[var(--app-color-surface-muted)] px-4 py-3"
              :style="{ borderColor: 'var(--app-color-border)' }"
            >
              <AppText size="sm" tone="subtle">Cuenta virtual</AppText>
              <p class="mt-2 text-sm font-semibold text-[var(--app-color-text)]">
                {{ account.isVirtual ? 'Sí' : 'No' }}
              </p>
            </div>

            <div
              class="rounded-2xl border bg-[var(--app-color-surface-muted)] px-4 py-3"
              :style="{ borderColor: 'var(--app-color-border)' }"
            >
              <div class="flex items-center justify-between gap-3">
                <div class="space-y-1">
                  <AppText size="sm" tone="subtle">Cuenta de alimentación</AppText>
                  <p class="text-sm font-semibold text-[var(--app-color-text)]">
                    {{ account.fundingAccount ?? 'No configurada' }}
                  </p>
                </div>
                <span
                  class="inline-flex items-center rounded-full px-2.5 py-1 text-[10px] font-semibold uppercase tracking-[0.04em]"
                  :class="
                    account.fundingAccount
                      ? 'bg-emerald-500/12 text-emerald-600'
                      : 'bg-slate-500/12 text-slate-600'
                  "
                >
                  {{ account.fundingAccount ? 'Configurada' : 'Sin cuenta' }}
                </span>
              </div>
            </div>
          </div>
        </div>
      </AppCard>

      <AppCard class="rounded-3xl">
        <div class="space-y-4">
          <div class="flex items-start justify-between gap-4">
            <div class="space-y-1">
              <AppTitle as="h2" size="sm">Usuarios de la cuenta</AppTitle>
              <AppText>
                Resumen operativo para reparto semi-automático y control de egresos pendientes.
              </AppText>
            </div>
            <div class="text-right">
              <p class="text-sm font-semibold text-[var(--app-color-text)]">
                {{ account.users.length }}
              </p>
              <AppText size="sm" tone="subtle">usuarios</AppText>
            </div>
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div
              class="rounded-2xl border bg-[var(--app-color-surface-muted)] px-4 py-3"
              :style="{ borderColor: 'var(--app-color-border)' }"
            >
              <AppText size="sm" tone="subtle">Con saldo pendiente</AppText>
              <p class="mt-1 text-lg font-semibold text-[var(--app-color-text)]">
                {{ usersPendingSummary }}
              </p>
            </div>

            <div
              class="rounded-2xl border bg-[var(--app-color-surface-muted)] px-4 py-3"
              :style="{ borderColor: 'var(--app-color-border)' }"
            >
              <AppText size="sm" tone="subtle">Reparto base</AppText>
              <p class="mt-1 text-lg font-semibold text-[var(--app-color-text)]">
                {{ account.users.length > 0 ? 'Activo' : 'Sin usuarios' }}
              </p>
            </div>
          </div>

          <div class="space-y-3">
            <div
              v-for="user in account.users"
              :key="user.id"
              class="rounded-2xl border bg-[var(--app-color-surface-muted)] px-4 py-4"
              :style="{ borderColor: 'var(--app-color-border)' }"
            >
              <div class="flex items-start justify-between gap-4">
                <div class="min-w-0 space-y-1">
                  <p class="truncate text-sm font-semibold text-[var(--app-color-text)]">
                    {{ user.name }}
                  </p>
                  <AppText size="sm" class="truncate">{{ user.email }}</AppText>
                </div>

                <div class="shrink-0 text-right">
                  <p class="text-sm font-semibold text-[var(--app-color-text)]">
                    {{ user.allocationPercentage }}%
                  </p>
                  <AppText size="sm" tone="subtle">reparto</AppText>
                </div>
              </div>

              <div class="mt-4 flex items-center justify-between gap-3">
                <AppText size="sm" tone="subtle">Egresos pendientes</AppText>
                <p class="text-sm font-semibold tabular-nums text-[var(--app-color-text)]">
                  {{ user.pendingExpenses }}
                </p>
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
    </section>

    <section v-else-if="activeSection === 'transactions'" class="space-y-4">
      <AppCard class="rounded-3xl">
        <div class="space-y-1">
          <AppTitle as="h2" size="sm">Transacciones</AppTitle>
          <AppText>Vista embebida para revisar actividad y conciliación de la cuenta.</AppText>
        </div>
      </AppCard>

      <AppCard v-for="transaction in transactionItems" :key="transaction.title" class="rounded-3xl">
        <div class="flex items-start justify-between gap-4">
          <div class="space-y-1">
            <p class="text-sm font-semibold text-[var(--app-color-text)]">
              {{ transaction.title }}
            </p>
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

      <AppCard v-for="invitation in invitationItems" :key="invitation.title" class="rounded-3xl">
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

    <div
      class="fixed bottom-[5rem] left-1/2 z-10 w-full max-w-[430px] -translate-x-1/2 border-t border-[var(--app-color-border)] bg-[color-mix(in_srgb,var(--app-color-surface)_96%,transparent)] backdrop-blur"
    >
      <AppContextTabs
        :model-value="activeSection"
        :options="relationshipSections"
        indicator-position="top"
        @update:model-value="updateActiveSection"
      />
    </div>

    <AppModal
      :open="isDeleteModalOpen"
      title="Eliminar cuenta"
      close-label="Cancelar"
      @close="closeDeleteModal"
    >
      <div class="space-y-4">
        <AppText>
          Vas a eliminar <strong>{{ account.name }}</strong
          >. Esta acción debe confirmar dependencias, usuarios y metas financieras antes de
          ejecutarse.
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
