<script setup lang="ts">
import FacilityInvitationListItem from '@/modules/admin/components/FacilityInvitationListItem.vue';
import type { AccountInvite } from '@/modules/accounts/schemas/accountInviteSchemas';
import { AppEmptyState, AppText } from '@/modules/shared/components';

const props = defineProps<{
  invitations: AccountInvite[];
}>();

const emit = defineEmits<{
  accept: [invitationId: string];
  reject: [invitationId: string];
}>();

function resolveAccountName(invitation: AccountInvite): string {
  return invitation.accountName ?? `Cuenta #${invitation.accountId}`;
}

function resolveInvitedBy(invitation: AccountInvite): string {
  if (invitation.invitedByName) {
    return invitation.invitedByName;
  }

  if (invitation.userId) {
    return `Usuario #${invitation.userId}`;
  }

  return 'Invitador no disponible';
}

function resolveMetaLabel(invitation: AccountInvite): string {
  if (!invitation.invitedAt) {
    return 'Fecha no disponible';
  }

  const invitedAt = new Date(invitation.invitedAt);

  if (Number.isNaN(invitedAt.getTime())) {
    return 'Fecha no disponible';
  }

  return `Recibida ${new Intl.DateTimeFormat('es-MX', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
  }).format(invitedAt)}`;
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
      <FacilityInvitationListItem
        v-for="invitation in props.invitations"
        :key="invitation.id"
        :account-name="resolveAccountName(invitation)"
        :invited-by="resolveInvitedBy(invitation)"
        :item-id="invitation.id"
        :meta-label="resolveMetaLabel(invitation)"
        :status="invitation.status"
        @accept="emit('accept', $event)"
        @reject="emit('reject', $event)"
      />

      <AppEmptyState
        v-if="props.invitations.length === 0"
        message="No hay invitaciones que coincidan con la búsqueda."
      />

      <slot name="footer" />
    </div>
  </section>
</template>
