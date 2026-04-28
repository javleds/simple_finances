<script setup lang="ts">
import { computed, ref } from 'vue';
import { findAccountById, type AccountRecord } from '@/modules/accounts/data/accounts';
import { useRoute } from 'vue-router';
import AppButton from '@/modules/shared/components/AppButton.vue';
import { PencilSquareIcon, TrashIcon } from '@heroicons/vue/24/outline';
import AppCard from '@/modules/shared/components/AppCard.vue';
import AppTitle from '@/modules/shared/components/AppTitle.vue';
import AppText from '@/modules/shared/components/AppText.vue';

const route = useRoute();
const isDeleteModalOpen = ref(false);

const account = computed(() => {
  const accountId = typeof route.params.accountId === 'string' ? route.params.accountId : '';
  return findAccountById(accountId);
});

const usersPendingSummary = computed(() => {
  if (!account.value) {
    return 0;
  }

  return account.value.users.filter((user) => user.pendingExpenses !== '$0.00').length;
});

function openDeleteModal(): void {
  isDeleteModalOpen.value = true;
}

function statusClasses(status: AccountRecord['status']): string {
  if (status === 'Activo') {
    return 'bg-emerald-500/12 text-emerald-600';
  }

  return 'bg-slate-500/12 text-slate-600 dark:text-slate-300';
}
</script>

<template>
  <section v-if="account" class="space-y-5">
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
        <div class="flex items-start justify-between gap-4">
          <div class="min-w-0 space-y-2">
            <div class="flex items-center gap-3">
              <span class="h-3 w-3 rounded-sm" :style="{ backgroundColor: account.color }" />
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
            <p class="mt-1 text-2xl font-semibold tracking-tight text-(--app-color-text)">
              {{ account.balance }}
            </p>
          </div>
        </div>

        <div v-if="account.accountType === 'credito'" class="grid grid-cols-2 gap-3">
          <div
            class="rounded-2xl border bg-(--app-color-surface-muted) px-4 py-3"
            :style="{ borderColor: 'var(--app-color-border)' }"
          >
            <AppText size="sm" tone="subtle">Línea de crédito</AppText>
            <p class="mt-1 text-lg font-semibold text-(--app-color-text)">
              {{ account.creditLine }}
            </p>
          </div>

          <div
            class="rounded-2xl border bg-(--app-color-surface-muted) px-4 py-3"
            :style="{ borderColor: 'var(--app-color-border)' }"
          >
            <AppText size="sm" tone="subtle">Crédito disponible</AppText>
            <p class="mt-1 text-lg font-semibold text-(--app-color-text)">
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
            class="rounded-2xl border bg-(--app-color-surface-muted) px-4 py-3"
            :style="{ borderColor: 'var(--app-color-border)' }"
          >
            <AppText size="sm" tone="subtle">Color</AppText>
            <div class="mt-2 flex items-center gap-3">
              <span
                class="h-4 w-4 rounded-sm border border-white/30"
                :style="{ backgroundColor: account.color }"
              />
              <p class="text-sm font-semibold text-(--app-color-text)">
                {{ account.color }}
              </p>
            </div>
          </div>

          <div
            class="rounded-2xl border bg-(--app-color-surface-muted) px-4 py-3"
            :style="{ borderColor: 'var(--app-color-border)' }"
          >
            <AppText size="sm" tone="subtle">Tipo de cuenta</AppText>
            <p class="mt-2 text-sm font-semibold text-(--app-color-text)">
              {{ account.accountType === 'credito' ? 'Crédito' : 'Débito' }}
            </p>
          </div>

          <div
            class="rounded-2xl border bg-(--app-color-surface-muted) px-4 py-3"
            :style="{ borderColor: 'var(--app-color-border)' }"
          >
            <AppText size="sm" tone="subtle">Cuenta virtual</AppText>
            <p class="mt-2 text-sm font-semibold text-(--app-color-text)">
              {{ account.isVirtual ? 'Sí' : 'No' }}
            </p>
          </div>

          <div
            class="rounded-2xl border bg-(--app-color-surface-muted) px-4 py-3"
            :style="{ borderColor: 'var(--app-color-border)' }"
          >
            <div class="flex items-center justify-between gap-3">
              <div class="space-y-1">
                <AppText size="sm" tone="subtle">Cuenta de alimentación</AppText>
                <p class="text-sm font-semibold text-(--app-color-text)">
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
            <p class="text-sm font-semibold text-(--app-color-text)">
              {{ account.users.length }}
            </p>
            <AppText size="sm" tone="subtle">usuarios</AppText>
          </div>
        </div>

        <div class="grid grid-cols-2 gap-3">
          <div
            class="rounded-2xl border bg-(--app-color-surface-muted) px-4 py-3"
            :style="{ borderColor: 'var(--app-color-border)' }"
          >
            <AppText size="sm" tone="subtle">Con saldo pendiente</AppText>
            <p class="mt-1 text-lg font-semibold text-(--app-color-text)">
              {{ usersPendingSummary }}
            </p>
          </div>

          <div
            class="rounded-2xl border bg-(--app-color-surface-muted) px-4 py-3"
            :style="{ borderColor: 'var(--app-color-border)' }"
          >
            <AppText size="sm" tone="subtle">Reparto base</AppText>
            <p class="mt-1 text-lg font-semibold text-(--app-color-text)">
              {{ account.users.length > 0 ? 'Activo' : 'Sin usuarios' }}
            </p>
          </div>
        </div>

        <div class="space-y-3">
          <div
            v-for="user in account.users"
            :key="user.id"
            class="rounded-2xl border bg-(--app-color-surface-muted) px-4 py-4"
            :style="{ borderColor: 'var(--app-color-border)' }"
          >
            <div class="flex items-start justify-between gap-4">
              <div class="min-w-0 space-y-1">
                <p class="truncate text-sm font-semibold text-(--app-color-text)">
                  {{ user.name }}
                </p>
                <AppText size="sm" class="truncate">{{ user.email }}</AppText>
              </div>

              <div class="shrink-0 text-right">
                <p class="text-sm font-semibold text-(--app-color-text)">
                  {{ user.allocationPercentage }}%
                </p>
                <AppText size="sm" tone="subtle">reparto</AppText>
              </div>
            </div>

            <div class="mt-4 flex items-center justify-between gap-3">
              <AppText size="sm" tone="subtle">Egresos pendientes</AppText>
              <p class="text-sm font-semibold tabular-nums text-(--app-color-text)">
                {{ user.pendingExpenses }}
              </p>
            </div>
          </div>
        </div>
      </div>
    </AppCard>
  </section>
</template>
