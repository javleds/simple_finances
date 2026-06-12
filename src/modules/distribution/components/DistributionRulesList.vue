<script setup lang="ts">
import DistributionRuleListItem from '@/modules/distribution/components/DistributionRuleListItem.vue';
import type { DistributionRule } from '@/modules/distribution/types';
import { AppEmptyState, AppText } from '@/modules/shared/components';

const props = defineProps<{
  rules: DistributionRule[];
}>();

const emit = defineEmits<{
  delete: [ruleId: string];
  edit: [ruleId: string];
}>();
</script>

<template>
  <section class="space-y-3">
    <div class="flex items-center justify-between gap-3">
      <AppText size="sm" tone="subtle">{{ props.rules.length }} reglas visibles</AppText>
      <AppText size="sm" tone="subtle">Scroll continuo</AppText>
    </div>

    <div class="space-y-4">
      <DistributionRuleListItem
        v-for="rule in props.rules"
        :key="rule.id"
        :frequency="rule.frequency"
        :item-id="rule.id"
        :name="rule.name"
        :outcomes-count="rule.outcomesCount"
        :total-amount="rule.totalAmount"
        @delete="emit('delete', $event)"
        @edit="emit('edit', $event)"
      />

      <AppEmptyState
        v-if="props.rules.length === 0"
        message="No hay reglas que coincidan con la búsqueda o filtros actuales."
      />

      <slot name="footer" />
    </div>
  </section>
</template>
