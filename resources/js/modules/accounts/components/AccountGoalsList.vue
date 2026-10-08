<script setup lang="ts">
import AccountGoalListItem from '@/modules/accounts/components/AccountGoalListItem.vue';
import type { AccountGoal } from '@/modules/accounts/schemas/accountGoalSchemas';
import { AppEmptyState, AppText } from '@/modules/shared/components';

type GoalDisplayStatus = 'on-track' | 'at-risk' | 'completed';

const props = defineProps<{
  goals: AccountGoal[];
}>();

const emit = defineEmits<{
  delete: [goalId: string];
  edit: [goalId: string];
}>();

function resolveGoalStatus(progress: number): GoalDisplayStatus {
  if (progress >= 100) {
    return 'completed';
  }

  if (progress < 50) {
    return 'at-risk';
  }

  return 'on-track';
}

function formatDateLabel(date: string | null): string {
  if (!date) {
    return 'Sin fecha límite';
  }

  const normalizedDate = /^\d{4}-\d{2}-\d{2}$/.test(date) ? `${date}T00:00:00` : date;
  const parsedDate = new Date(normalizedDate);

  if (Number.isNaN(parsedDate.getTime())) {
    return 'Sin fecha límite';
  }

  return new Intl.DateTimeFormat('es-MX', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
  }).format(parsedDate);
}
</script>

<template>
  <section class="space-y-3">
    <div class="flex items-center justify-between gap-3">
      <AppText size="sm" tone="subtle">{{ props.goals.length }} metas visibles</AppText>
      <AppText size="sm" tone="subtle">Scroll continuo</AppText>
    </div>

    <div class="space-y-4">
      <AccountGoalListItem
        v-for="goal in props.goals"
        :key="goal.id"
        :achieved-amount="goal.achievedAmount"
        :deadline-label="formatDateLabel(goal.deadline)"
        :item-id="goal.id"
        :owner-label="goal.status === 'completed' ? 'Meta completada' : 'Meta en progreso'"
        :progress="goal.progress"
        :status="goal.status === 'completed' ? 'completed' : resolveGoalStatus(goal.progress)"
        :target-amount="goal.amount"
        :title="goal.name"
        @delete="emit('delete', $event)"
        @edit="emit('edit', $event)"
      />

      <AppEmptyState
        v-if="props.goals.length === 0"
        message="No hay metas que coincidan con la búsqueda o los filtros actuales."
      />

      <slot name="footer" />
    </div>
  </section>
</template>
