<script setup lang="ts">
import {
  AdjustmentsHorizontalIcon,
  MagnifyingGlassIcon,
  PlusIcon,
} from '@heroicons/vue/24/outline';
import { computed, ref } from 'vue';

import AccountListItem from '@/modules/accounts/components/AccountListItem.vue';
import { accounts } from '@/modules/accounts/data/accounts';
import {
  AppButton,
  AppCard,
  AppIconButton,
  AppInput,
  AppModal,
  AppText,
  AppTitle,
} from '@/modules/shared/components';

const searchTerm = ref('');
const isFiltersOpen = ref(false);
const selectedStatuses = ref<string[]>([]);

const statusOptions = ['Activa', 'Alineada', 'Programada', 'En revisión'] as const;

const filteredAccounts = computed(() => {
  const normalizedQuery = searchTerm.value.trim().toLowerCase();

  return accounts.filter((account) => {
    const matchesQuery = account.name.toLowerCase().includes(normalizedQuery);

    if (!matchesQuery) {
      return false;
    }

    if (selectedStatuses.value.length === 0) {
      return true;
    }

    return selectedStatuses.value.includes(account.status);
  });
});

function toggleStatus(status: string): void {
  if (selectedStatuses.value.includes(status)) {
    selectedStatuses.value = selectedStatuses.value.filter((item) => item !== status);
    return;
  }

  selectedStatuses.value = [...selectedStatuses.value, status];
}

function openFilters(): void {
  isFiltersOpen.value = true;
}

function closeFilters(): void {
  isFiltersOpen.value = false;
}

function clearFilters(): void {
  selectedStatuses.value = [];
}
</script>

<template>
  <div class="space-y-5">
    <div class="flex items-center justify-between gap-3">
      <div class="space-y-1">
        <AppTitle as="h2" size="sm">Gestión de cuentas</AppTitle>
        <AppText>La navegación por default es entrar al detalle de cada cuenta.</AppText>
      </div>

      <AppButton variant="primary">
        <PlusIcon class="h-4 w-4" />
      </AppButton>
    </div>

    <div class="flex items-end gap-3">
      <div class="relative flex-1">
        <div
          class="pointer-events-none absolute inset-y-0 left-4 flex items-center text-[var(--app-color-text-subtle)]"
        >
          <MagnifyingGlassIcon class="h-5 w-5" />
        </div>
        <AppInput
          id="account-search"
          v-model="searchTerm"
          type="search"
          placeholder="Buscar cuenta por nombre"
          class="pl-11"
        />
      </div>

      <AppIconButton ariaLabel="Abrir filtros avanzados" @click="openFilters">
        <AdjustmentsHorizontalIcon class="h-5 w-5" />
      </AppIconButton>
    </div>

    <section class="space-y-3">
      <div class="flex items-center justify-between gap-3">
        <AppText size="sm" tone="subtle"> {{ filteredAccounts.length }} cuentas visibles </AppText>
        <AppText size="sm" tone="subtle">Scroll continuo</AppText>
      </div>

      <div class="max-h-[30rem] space-y-4 overflow-y-auto pr-1">
        <AccountListItem v-for="account in filteredAccounts" :key="account.id" :account="account" />

        <div
          class="rounded-2xl border border-dashed px-4 py-4 text-center"
          :style="{ borderColor: 'var(--app-color-border)' }"
        >
          <AppText size="sm">
            Sigue desplazándote para explorar más cuentas cuando la facility crezca.
          </AppText>
        </div>
      </div>
    </section>

    <AppModal
      :open="isFiltersOpen"
      title="Filtros avanzados"
      close-label="Cerrar"
      @close="closeFilters"
    >
      <div class="space-y-5">
        <div class="space-y-2">
          <AppTitle as="h2" size="sm">Estatus</AppTitle>
          <AppText>Refina la lista usando el estado operativo de la cuenta.</AppText>
        </div>

        <div class="flex flex-wrap gap-2">
          <button
            v-for="status in statusOptions"
            :key="status"
            type="button"
            class="rounded-full border px-4 py-2 text-sm font-medium transition focus:outline-none focus:ring-4 focus:ring-[var(--app-color-focus-ring)]"
            :class="
              selectedStatuses.includes(status)
                ? 'bg-[var(--app-color-primary)] text-[var(--app-color-primary-foreground)]'
                : 'bg-[var(--app-color-surface-muted)] text-[var(--app-color-text)]'
            "
            :style="{ borderColor: 'var(--app-color-border)' }"
            @click="toggleStatus(status)"
          >
            {{ status }}
          </button>
        </div>

        <div class="flex justify-end">
          <AppButton variant="outline" @click="clearFilters">Limpiar filtros</AppButton>
        </div>
      </div>
    </AppModal>
  </div>
</template>
