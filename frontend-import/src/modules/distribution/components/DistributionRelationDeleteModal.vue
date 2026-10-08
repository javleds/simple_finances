<script setup lang="ts">
import type { AppModalAction } from '@/modules/shared/types/modal';
import type { DistributionRelation } from '@/modules/distribution/types';
import { AppModal, AppText } from '@/modules/shared/components';

const props = withDefaults(
  defineProps<{
    actions: ReadonlyArray<AppModalAction>;
    deleteError?: string | null;
    open: boolean;
    relation: DistributionRelation | null;
  }>(),
  {
    deleteError: null,
  },
);

const emit = defineEmits<{
  close: [];
  confirm: [];
}>();
</script>

<template>
  <AppModal
    :open="props.open"
    :actions="props.actions"
    title="Eliminar relación"
    variant="danger"
    @action="$event === 'confirm-delete-relation' && emit('confirm')"
    @close="emit('close')"
  >
    <div class="space-y-3">
      <AppText v-if="props.relation">
        Vas a eliminar <strong>{{ props.relation.name }}</strong
        >.
      </AppText>
      <AppText v-if="props.deleteError" class="text-(--app-color-danger)!">
        {{ props.deleteError }}
      </AppText>
    </div>
  </AppModal>
</template>
