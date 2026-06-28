<script setup lang="ts">
import { ArrowsRightLeftIcon, EnvelopeIcon, FlagIcon, UsersIcon } from '@heroicons/vue/24/outline';
import { computed, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';

import { createAccountsRepository } from '@/modules/accounts/repositories/accountsRepository';
import type { Account, AccountMember } from '@/modules/accounts/types';
import { AppCard, AppContextTabs, AppLink, AppText, AppTitle } from '@/modules/shared/components';
import { resolveApiErrorMessage } from '@/modules/shared/lib/apiErrors';

type AccountRelationSection = 'transactions' | 'invitations' | 'goals' | 'users';

const route = useRoute();
const router = useRouter();
const accountsRepository = createAccountsRepository();
const account = ref<Account | null>(null);
const isLoadingAccount = ref(false);
const loadError = ref<string | null>(null);

const accountId = computed(() =>
  typeof route.params.accountId === 'string' ? route.params.accountId : '',
);

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

const activeSection = computed<AccountRelationSection>(
  () => (String(route.name).split('.').pop() as AccountRelationSection) || 'transactions',
);

watch(
  accountId,
  (nextAccountId) => {
    void loadAccount(nextAccountId);
  },
  { immediate: true },
);

async function loadAccount(nextAccountId: string): Promise<void> {
  if (!nextAccountId) {
    account.value = null;
    loadError.value = 'La cuenta solicitada no es válida.';
    return;
  }

  isLoadingAccount.value = true;
  loadError.value = null;

  try {
    account.value = await accountsRepository.getById(nextAccountId);
  } catch (error) {
    account.value = null;
    loadError.value = resolveApiErrorMessage(error, 'No fue posible cargar la cuenta.');
  } finally {
    isLoadingAccount.value = false;
  }
}

function updateActiveSection(nextSection: string): void {
  if (!accountId.value) {
    return;
  }

  router.push({
    name: `admin.accounts.${nextSection}`,
    params: {
      accountId: accountId.value,
    },
  });
}

function handleAccountUsersChange(nextUsers: AccountMember[]): void {
  if (!account.value) {
    return;
  }

  account.value = {
    ...account.value,
    users: [...nextUsers],
  };
}
</script>

<template>
  <div v-if="isLoadingAccount && !account" class="space-y-5 pb-16">
    <AppCard class="rounded-3xl">
      <div class="space-y-3">
        <AppTitle as="h2" size="sm">Cargando cuenta</AppTitle>
        <AppText>Estamos consultando el detalle más reciente de esta cuenta.</AppText>
      </div>
    </AppCard>
  </div>

  <div v-else-if="account" class="space-y-5 pb-16">
    <header class="space-y-1 px-1">
      <AppTitle as="h1">{{ account.name }}</AppTitle>
      <AppText v-if="account.description" tone="subtle">
        {{ account.description }}
      </AppText>
    </header>

    <RouterView v-slot="{ Component }">
      <component
        :is="Component"
        :account="account"
        :is-loading-account="isLoadingAccount"
        :account-load-error="loadError"
        v-bind="
          activeSection === 'users' ? { onAccountUsersChange: handleAccountUsersChange } : undefined
        "
      />
    </RouterView>

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
  </div>

  <AppCard v-else class="rounded-3xl">
    <div class="space-y-3">
      <AppTitle as="h2" size="sm">Cuenta no encontrada</AppTitle>
      <AppText>
        {{
          loadError ?? 'La cuenta solicitada no existe o ya no está disponible en esta facility.'
        }}
      </AppText>
      <AppLink :to="{ name: 'admin.accounts' }" variant="primary">Volver a la lista</AppLink>
    </div>
  </AppCard>
</template>
