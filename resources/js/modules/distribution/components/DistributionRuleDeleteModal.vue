<script setup lang="ts">
import type { AppModalAction } from '@/modules/shared/types/modal';
import type { DistributionRule } from '@/modules/distribution/types';
import { AppModal, AppText } from '@/modules/shared/components';

const props = withDefaults(
  defineProps<{
    actions: ReadonlyArray<AppModalAction>;
    deleteError?: string | null;
    open: boolean;
    rule: DistributionRule | null;
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
    title="Eliminar regla"
    variant="danger"
    @action="$event === 'confirm-delete-rule' && emit('confirm')"
    @close="emit('close')"
  >
    <div class="space-y-3">
      <AppText v-if="props.rule">
        Vas a eliminar <strong>{{ props.rule.name }}</strong
        >.
      </AppText>
      <AppText v-if="props.deleteError" class="text-(--app-color-danger)!">
        {{ props.deleteError }}
      </AppText>
    </div>
  </AppModal>
</template>
