<script setup lang="ts">
import AccountInvitationListItem from '@/modules/accounts/components/AccountInvitationListItem.vue';
import type { AccountInvite } from '@/modules/accounts/schemas/accountInviteSchemas';
import { AppEmptyState, AppText } from '@/modules/shared/components';

const props = defineProps<{
  invitations: AccountInvite[];
}>();

const emit = defineEmits<{
  delete: [invitationId: string];
  edit: [invitationId: string];
}>();

function formatDateLabel(date: string | null): string {
  if (!date) {
    return 'Sin fecha';
  }

  return new Intl.DateTimeFormat('es-MX', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
  }).format(new Date(date));
}
</script>

<template>
  <section class="space-y-3">
    <div class="flex items-center justify-between gap-3">
      <AppText size="sm" tone="subtle">
        {{ props.invitations.length }} invitaciones visibles
      </AppText>
      <AppText size="sm" tone="subtle">Scroll continuo</AppText>
    </div>

    <div class="space-y-4">
      <AccountInvitationListItem
        v-for="invitation in props.invitations"
        :key="invitation.id"
        :email="invitation.email"
        :item-id="invitation.id"
        :meta-label="formatDateLabel(invitation.invitedAt)"
        :percentage-label="`${invitation.percentage}%`"
        :status="invitation.status"
        @delete="emit('delete', $event)"
        @edit="emit('edit', $event)"
      />

      <AppEmptyState
        v-if="props.invitations.length === 0"
        message="No hay invitaciones que coincidan con la búsqueda o los filtros actuales."
      />

      <slot name="footer" />
    </div>
  </section>
</template>
