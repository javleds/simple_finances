<script setup lang="ts">
import AccountUserListItem from '@/modules/accounts/components/AccountUserListItem.vue';
import type { AccountMember, AccountVirtualCustodian } from '@/modules/accounts/types';
import { AppEmptyState, AppText } from '@/modules/shared/components';

const props = defineProps<{
  canManage?: boolean;
  canManageCustodians?: boolean;
  users: AccountMember[];
  custodians?: AccountVirtualCustodian[];
}>();

const emit = defineEmits<{
  delete: [userId: string];
  edit: [userId: string];
  renameCustodian: [custodianId: string];
  deleteCustodian: [custodianId: string];
}>();
</script>

<template>
  <section class="space-y-3">
    <div class="flex items-center justify-between gap-3">
      <AppText size="sm" tone="subtle"
        >{{ props.users.length + (props.custodians?.length ?? 0) }} usuarios visibles</AppText
      >
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

      <AccountUserListItem
        v-for="custodian in props.custodians ?? []"
        :key="`custodian-${custodian.id}`"
        :can-manage="props.canManageCustodians === true"
        virtual-custodian
        access-label="Sin acceso"
        :allocation-percentage="0"
        email=""
        :item-id="custodian.id"
        :name="custodian.name"
        :custody-amount="custodian.custodyAmount"
        :settlement-amount="custodian.settlementAmount"
        role-label="Custodio virtual"
        status="active"
        @delete="emit('deleteCustodian', $event)"
        @edit="emit('renameCustodian', $event)"
      />

      <AppEmptyState
        v-if="props.users.length === 0 && !props.custodians?.length"
        message="No hay usuarios que coincidan con la búsqueda actual."
      />

      <slot name="footer" />
    </div>
  </section>
</template>
