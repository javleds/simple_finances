<script setup lang="ts">
import AccountUserListItem from '@/modules/accounts/components/AccountUserListItem.vue';
import type { AccountMember } from '@/modules/accounts/types';
import { AppEmptyState, AppText } from '@/modules/shared/components';

const props = defineProps<{
  canManage?: boolean;
  users: AccountMember[];
}>();

const emit = defineEmits<{
  delete: [userId: string];
  edit: [userId: string];
}>();
</script>

<template>
  <section class="space-y-3">
    <div class="flex items-center justify-between gap-3">
      <AppText size="sm" tone="subtle">{{ props.users.length }} usuarios visibles</AppText>
      <AppText size="sm" tone="subtle">Scroll continuo</AppText>
    </div>

    <div class="space-y-4">
      <AccountUserListItem
        v-for="user in props.users"
        :key="user.id"
        :can-manage="props.canManage"
        access-label="Cuenta compartida"
        :allocation-percentage="user.allocationPercentage"
        :email="user.email"
        :item-id="user.id"
        :name="user.name"
        :custody-amount="user.custodyAmount"
        :settlement-amount="user.settlementAmount"
        role-label="Miembro"
        status="active"
        @delete="emit('delete', $event)"
        @edit="emit('edit', $event)"
      />

      <AppEmptyState
        v-if="props.users.length === 0"
        message="No hay usuarios que coincidan con la búsqueda actual."
      />

      <slot name="footer" />
    </div>
  </section>
</template>
