<script setup lang="ts">
import {
    ArrowsRightLeftIcon,
    BookOpenIcon,
    EnvelopeIcon,
    FlagIcon,
    UsersIcon,
} from '@heroicons/vue/24/outline';
import { computed } from 'vue';
import { useRoute, useRouter } from 'vue-router';

import { useAccountRelationshipAccount } from '@/modules/accounts/composables/useAccountRelationshipAccount';
import type { AccountMember } from '@/modules/accounts/types';
import { AppCard, AppContextTabs, AppLink, AppText, AppTitle } from '@/modules/shared/components';

type AccountRelationSection = 'transactions' | 'ledger' | 'invitations' | 'goals' | 'users';

const route = useRoute();
const router = useRouter();

const accountId = computed(() =>
    typeof route.params.accountId === 'string' ? route.params.accountId : '',
);
const { account, isLoadingAccount, loadError, setAccountUsers } =
    useAccountRelationshipAccount(accountId);

const relationshipSections = [
    {
        value: 'transactions',
        label: 'Transacciones',
        icon: ArrowsRightLeftIcon,
    },
    {
        value: 'ledger',
        label: 'Libro',
        icon: BookOpenIcon,
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
    setAccountUsers(nextUsers);
}
</script>

<template>
    <div v-if="isLoadingAccount && !account" class="space-y-6 sm:space-y-5 sm:pb-16 lg:pb-0">
        <AppCard class="rounded-(--app-radius-control)">
            <div class="space-y-3">
                <AppTitle as="h2" size="sm">Cargando cuenta</AppTitle>
                <AppText>Estamos consultando el detalle más reciente de esta cuenta.</AppText>
            </div>
        </AppCard>
    </div>

    <div v-else-if="account" class="space-y-6 sm:space-y-5 sm:pb-16 lg:pb-0">
        <header class="space-y-1">
            <AppTitle as="h1" class="break-words">{{ account.name }}</AppTitle>
            <AppText v-if="account.description" tone="subtle">
                {{ account.description }}
            </AppText>
        </header>

        <div
            class="sticky top-(--app-header-height) z-10 -mx-4 border-b border-(--app-color-border) bg-(--app-color-surface) sm:fixed sm:top-auto sm:bottom-(--app-bottom-nav-height) sm:left-1/2 sm:mx-0 sm:mb-0! sm:w-full sm:max-w-[430px] sm:-translate-x-1/2 sm:border-t lg:static lg:w-auto lg:max-w-none lg:translate-x-0 lg:border-t-0"
        >
            <AppContextTabs
                :model-value="activeSection"
                :options="relationshipSections"
                indicator-position="top"
                @update:model-value="updateActiveSection"
            />
        </div>

        <div class="lg:pt-6">
            <RouterView v-slot="{ Component }">
                <component
                    :is="Component"
                    :account="account"
                    :is-loading-account="isLoadingAccount"
                    :account-load-error="loadError"
                    v-bind="
                        activeSection === 'users'
                            ? { onAccountUsersChange: handleAccountUsersChange }
                            : undefined
                    "
                />
            </RouterView>
        </div>
    </div>

    <AppCard v-else class="rounded-(--app-radius-control)">
        <div class="space-y-3">
            <AppTitle as="h2" size="sm">Cuenta no encontrada</AppTitle>
            <AppText>
                {{
                    loadError ??
                    'La cuenta solicitada no existe o ya no está disponible en esta facility.'
                }}
            </AppText>
            <AppLink :to="{ name: 'admin.accounts' }" variant="primary">Volver a la lista</AppLink>
        </div>
    </AppCard>
</template>
