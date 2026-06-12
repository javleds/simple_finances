<script setup lang="ts">
import {
  ArrowPathIcon,
  CheckIcon,
} from '@heroicons/vue/24/outline';

import type { AccountPendingByUser } from '@/modules/accounts/types';
import {
  AppAvatarValueRow,
  AppCard,
  AppHeroMetric,
  AppIconButton,
  AppText,
} from '@/modules/shared/components';

const props = defineProps<{
  balance: number;
  currentUserId: string | null;
  isCompletingPendingByUser: boolean;
  isSharedAccount: boolean;
  pendingUsers: AccountPendingByUser[];
}>();

const emit = defineEmits<{
  completePendingUser: [userId: string];
}>();

function formatCurrency(value: number): string {
  return new Intl.NumberFormat('es-MX', {
    style: 'currency',
    currency: 'MXN',
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(value);
}
</script>

<template>
  <AppCard class="rounded-3xl">
    <div class="space-y-5">
      <AppHeroMetric label="Balance" :value="formatCurrency(props.balance)">
        <template #adornment>
          <div
            class="flex h-12 w-12 items-center justify-center rounded-full border bg-(--app-color-surface-muted)"
            :style="{ borderColor: 'var(--app-color-border)' }"
          >
            <ArrowPathIcon class="h-5 w-5 text-(--app-color-text-subtle)" />
          </div>
        </template>
      </AppHeroMetric>

      <template v-if="props.isSharedAccount">
        <div class="border-t" :style="{ borderColor: 'var(--app-color-border)' }"></div>

        <div class="space-y-3">
          <AppText size="sm" tone="subtle">Pendientes por usuario</AppText>

          <div v-if="props.pendingUsers.length > 0" class="space-y-2">
            <AppAvatarValueRow
              v-for="user in props.pendingUsers"
              :key="user.userId"
              :name="user.userName"
              :value="formatCurrency(user.amount)"
            >
              <template v-if="props.currentUserId === user.userId" #action>
                <AppIconButton
                  :ariaLabel="`Completar pendientes de ${user.userName}`"
                  :disabled="props.isCompletingPendingByUser"
                  @click="emit('completePendingUser', user.userId)"
                >
                  <CheckIcon class="h-4 w-4" />
                </AppIconButton>
              </template>
            </AppAvatarValueRow>
          </div>

          <div v-else class="rounded-2xl bg-(--app-color-surface-muted) px-3 py-3">
            <AppText size="sm" tone="subtle">No hay montos pendientes por usuario.</AppText>
          </div>
        </div>
      </template>
    </div>
  </AppCard>
</template>
