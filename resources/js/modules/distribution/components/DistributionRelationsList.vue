<script setup lang="ts">
import DistributionRelationListItem from '@/modules/distribution/components/DistributionRelationListItem.vue';
import type { DistributionRelation } from '@/modules/distribution/types';
import { AppEmptyState, AppText } from '@/modules/shared/components';

const props = defineProps<{
  relations: DistributionRelation[];
}>();

const emit = defineEmits<{
  delete: [relationId: string];
  edit: [relationId: string];
}>();
</script>

<template>
  <section class="space-y-3">
    <div class="flex items-center justify-between gap-3">
      <AppText size="sm" tone="subtle">{{ props.relations.length }} relaciones visibles</AppText>
      <AppText size="sm" tone="subtle">Scroll continuo</AppText>
    </div>

    <div class="space-y-4 lg:grid lg:grid-cols-2 lg:items-start lg:gap-4 lg:space-y-0">
      <DistributionRelationListItem
        v-for="relation in props.relations"
        :key="relation.id"
        :amount="relation.amount"
        :concept="relation.name"
        :type="relation.type"
        @delete="emit('delete', relation.id)"
        @edit="emit('edit', relation.id)"
      />

      <AppEmptyState
        class="lg:col-span-full"
        v-if="props.relations.length === 0"
        message="Esta regla todavía no tiene relaciones registradas."
      />

      <div v-if="$slots.footer" class="lg:col-span-full"><slot name="footer" /></div>
    </div>
  </section>
</template>
