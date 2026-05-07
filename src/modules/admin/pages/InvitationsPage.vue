<script setup lang="ts">
import { MagnifyingGlassIcon, XMarkIcon } from '@heroicons/vue/24/outline';
import { computed, onMounted, ref } from 'vue';

import FacilityInvitationListItem from '@/modules/admin/components/FacilityInvitationListItem.vue';
import { createAccountInvitesRepository } from '@/modules/accounts/repositories/accountInvitesRepository';
import type { AccountInvite } from '@/modules/accounts/schemas/accountInviteSchemas';
import { ApiError } from '@/lib/api/apiClient';
import {
  AppButton,
  AppInput,
  AppModal,
  AppText,
  AppTitle,
} from '@/modules/shared/components';

type PendingInvitationAction = 'accepted' | 'declined';

const accountInvitesRepository = createAccountInvitesRepository();

const searchTerm = ref('');
const invitations = ref<AccountInvite[]>([]);
const isLoading = ref(false);
const isSaving = ref(false);
const loadError = ref<string | null>(null);
const saveError = ref<string | null>(null);
const selectedInvitationId = ref<string | null>(null);
const pendingAction = ref<PendingInvitationAction | null>(null);

const selectedInvitation = computed(() => {
  if (!selectedInvitationId.value) {
    return null;
  }

  return invitations.value.find((invitation) => invitation.id === selectedInvitationId.value) ?? null;
});

const visibleInvitations = computed(() => {
  const normalizedQuery = searchTerm.value.trim().toLowerCase();

  return invitations.value.filter((invitation) => {
    if (invitation.status !== 'pending') {
      return false;
    }

    if (normalizedQuery.length === 0) {
      return true;
    }

    const accountName = resolveAccountName(invitation).toLowerCase();
    const invitedBy = resolveInvitedBy(invitation).toLowerCase();

    return accountName.includes(normalizedQuery) || invitedBy.includes(normalizedQuery);
  });
});

const actionModalTitle = computed(() => {
  if (pendingAction.value === 'accepted') {
    return 'Aceptar invitación';
  }

  if (pendingAction.value === 'declined') {
    return 'Rechazar invitación';
  }

  return 'Invitación';
});

const actionModalVariant = computed(() => {
  return pendingAction.value === 'accepted' ? 'success' : 'danger';
});

const actionModalActions = computed(() => [
  { key: 'close', label: 'Cancelar', tone: 'danger' as const, icon: XMarkIcon, autoClose: true },
  {
    key: 'confirm-invitation-action',
    label: isSaving.value
      ? 'Guardando...'
      : pendingAction.value === 'accepted'
        ? 'Aceptar invitación'
        : 'Rechazar invitación',
    tone: 'primary' as const,
    disabled: !selectedInvitation.value || !pendingAction.value || isSaving.value,
  },
]);

onMounted(() => {
  void loadInvitations();
});

function resolveErrorMessage(error: unknown, fallback: string): string {
  if (error instanceof ApiError) {
    return error.message;
  }

  if (error instanceof Error && error.message) {
    return error.message;
  }

  return fallback;
}

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
    return 'Invitación pendiente';
  }

  const invitedAt = new Date(invitation.invitedAt);

  if (Number.isNaN(invitedAt.getTime())) {
    return 'Invitación pendiente';
  }

  return `Recibida ${new Intl.DateTimeFormat('es-MX', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
  }).format(invitedAt)}`;
}

async function loadInvitations(): Promise<void> {
  isLoading.value = true;
  loadError.value = null;

  try {
    invitations.value = await accountInvitesRepository.listAll();
  } catch (error) {
    loadError.value = resolveErrorMessage(error, 'No fue posible cargar las invitaciones.');
  } finally {
    isLoading.value = false;
  }
}

function openInvitationAction(inviteId: string, action: PendingInvitationAction): void {
  saveError.value = null;
  selectedInvitationId.value = inviteId;
  pendingAction.value = action;
}

function closeInvitationAction(): void {
  selectedInvitationId.value = null;
  pendingAction.value = null;
  saveError.value = null;
}

async function confirmInvitationAction(): Promise<void> {
  if (!selectedInvitation.value || !pendingAction.value) {
    return;
  }

  isSaving.value = true;
  saveError.value = null;

  try {
    const updatedInvitation = await accountInvitesRepository.respond(selectedInvitation.value.id, {
      accountId: selectedInvitation.value.accountId,
      email: selectedInvitation.value.email,
      percentage: selectedInvitation.value.percentage,
      status: pendingAction.value,
    });

    invitations.value = invitations.value.map((invitation) =>
      invitation.id === updatedInvitation.id ? updatedInvitation : invitation,
    );

    closeInvitationAction();
  } catch (error) {
    saveError.value = resolveErrorMessage(error, 'No fue posible responder la invitación.');
  } finally {
    isSaving.value = false;
  }
}
</script>

<template>
  <section class="space-y-4">
    <div class="space-y-1">
      <AppTitle as="h2" size="sm">Invitaciones</AppTitle>
      <AppText>Revisa las cuentas a las que aún no te has unido y responde desde aquí.</AppText>
    </div>

    <div class="relative flex-1">
      <div
        class="pointer-events-none absolute inset-y-0 left-4 flex items-center text-(--app-color-text-subtle)"
      >
        <MagnifyingGlassIcon class="h-5 w-5" />
      </div>
      <AppInput
        id="facility-invitation-search"
        v-model="searchTerm"
        type="search"
        placeholder="Buscar invitación por cuenta o invitador"
        class="pl-11"
      />
    </div>

    <section v-if="loadError && invitations.length > 0" class="rounded-2xl border border-(--app-color-danger) px-4 py-3">
      <AppText class="text-(--app-color-danger)!">{{ loadError }}</AppText>
    </section>

    <section v-if="isLoading && invitations.length === 0" class="rounded-2xl border px-4 py-10 text-center">
      <AppText>Cargando invitaciones...</AppText>
    </section>

    <section
      v-else-if="loadError && invitations.length === 0"
      class="space-y-3 rounded-2xl border px-4 py-6 text-center"
    >
      <AppText>{{ loadError }}</AppText>
      <div class="flex justify-center">
        <AppButton variant="secondary" @click="loadInvitations">Reintentar</AppButton>
      </div>
    </section>

    <section v-else class="space-y-3">
      <div class="flex items-center justify-between gap-3">
        <AppText size="sm" tone="subtle">
          {{ visibleInvitations.length }} invitaciones visibles
        </AppText>
        <AppText size="sm" tone="subtle">Scroll continuo</AppText>
      </div>

      <div class="space-y-4">
        <FacilityInvitationListItem
          v-for="invitation in visibleInvitations"
          :key="invitation.id"
          :account-name="resolveAccountName(invitation)"
          :invited-by="resolveInvitedBy(invitation)"
          :item-id="invitation.id"
          :meta-label="resolveMetaLabel(invitation)"
          status="pending"
          @accept="openInvitationAction($event, 'accepted')"
          @reject="openInvitationAction($event, 'declined')"
        />

        <div
          v-if="visibleInvitations.length === 0"
          class="rounded-2xl border border-dashed px-4 py-4 text-center"
          :style="{ borderColor: 'var(--app-color-border)' }"
        >
          <AppText size="sm">No hay invitaciones pendientes que coincidan con la búsqueda.</AppText>
        </div>

        <div
          class="rounded-2xl border border-dashed px-4 py-4 text-center"
          :style="{ borderColor: 'var(--app-color-border)' }"
        >
          <AppText size="sm">
            Sigue desplazándote para revisar más invitaciones conforme se compartan nuevas cuentas.
          </AppText>
        </div>
      </div>
    </section>

    <AppModal
      :open="Boolean(selectedInvitation && pendingAction)"
      :actions="actionModalActions"
      :title="actionModalTitle"
      :variant="actionModalVariant"
      @action="$event === 'confirm-invitation-action' && confirmInvitationAction()"
      @close="closeInvitationAction"
    >
      <div class="space-y-3">
        <AppText v-if="selectedInvitation">
          {{ pendingAction === 'accepted' ? 'Vas a aceptar la invitación a' : 'Vas a rechazar la invitación a' }}
          <strong>{{ resolveAccountName(selectedInvitation) }}</strong>.
        </AppText>
        <AppText v-if="saveError" class="text-(--app-color-danger)!">{{ saveError }}</AppText>
      </div>
    </AppModal>
  </section>
</template>
