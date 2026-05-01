<script setup lang="ts">
import {
  ArrowsRightLeftIcon,
  EnvelopeIcon,
  FlagIcon,
  InformationCircleIcon,
  TrashIcon,
  UsersIcon,
  XMarkIcon,
} from '@heroicons/vue/24/outline';
import { computed, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';

import { findAccountById } from '@/modules/accounts/data/accounts';
import {
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
    value: 'view',
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

const activeSection = computed<AccountRelationSection>(
  () => (String(route.name).split('.').pop() as AccountRelationSection) || 'view',
);

function closeDeleteModal(): void {
  isDeleteModalOpen.value = false;
}

function handleDeleteModalAction(actionKey: string): void {
  if (actionKey === 'cancel') {
    closeDeleteModal();
    return;
  }

  if (actionKey === 'confirm-delete') {
    closeDeleteModal();
  }
}

function updateActiveSection(nextSection: string): void {
  const accountId = typeof route.params.accountId === 'string' ? route.params.accountId : '';

  if (accountId)! {
    return;
  }

  router.push({
    name: `admin.accounts.${nextSection}`,
    params: {
      accountId,
    },
  });
}
</script>

<template>
  <div v-if="account" class="space-y-5 pb-16">
    <RouterView />

    <div
      class="fixed bottom-[5rem] left-1/2 z-10 w-full max-w-[430px] -translate-x-1/2 border-t border-(--app-color-border) bg-[color-mix(in_srgb,var(--app-color-surface)_96%,transparent)] backdrop-blur"
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
      :actions="[
        { key: 'cancel', label: 'Cancelar', tone: 'danger', icon: XMarkIcon, autoClose: true },
        {
          key: 'confirm-delete',
          label: 'Confirmar eliminación',
          tone: 'primary',
          icon: TrashIcon,
        },
      ]"
      title="Eliminar cuenta"
      variant="danger"
      @action="handleDeleteModalAction"
      @close="closeDeleteModal"
    >
      <div class="space-y-4">
        <AppText>
          Vas a eliminar <strong>{{ account.name }}</strong
          >. Esta acción debe confirmar dependencias, usuarios y metas financieras antes de
          ejecutarse.
        </AppText>
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
