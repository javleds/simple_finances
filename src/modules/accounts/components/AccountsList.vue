<script setup lang="ts">
import AccountListItem from '@/modules/accounts/components/AccountListItem.vue';
import type { Account } from '@/modules/accounts/types';
import { AppEmptyState, AppText } from '@/modules/shared/components';

const props = defineProps<{
  accounts: Account[];
  currentUserId: string | null;
}>();

const emit = defineEmits<{
  delete: [accountId: string];
  edit: [accountId: string];
  leave: [accountId: string];
}>();
</script>

<template>
  <section class="space-y-3">
    <div class="flex items-center justify-between gap-3">
      <AppText size="sm" tone="subtle"> {{ props.accounts.length }} cuentas visibles </AppText>
      <AppText size="sm" tone="subtle">Scroll continuo</AppText>
    </div>

    <div class="space-y-4">
      <AccountListItem
        v-for="account in props.accounts"
        :key="account.id"
        :account="account"
        :current-user-id="props.currentUserId"
        @delete="emit('delete', $event)"
        @edit="emit('edit', $event)"
        @leave="emit('leave', $event)"
      />

      <AppEmptyState
        v-if="props.accounts.length === 0"
        message="No hay cuentas que coincidan con la búsqueda o los filtros actuales."
      />

      <slot name="footer" />
    </div>
  </section>
</template>
