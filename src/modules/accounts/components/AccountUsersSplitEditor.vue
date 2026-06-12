<script setup lang="ts">
import { AppButton, AppCard, AppPercentageSplitEditor, AppText } from '@/modules/shared/components';

type SplitUser = {
  id: string;
  name: string;
};

const splitDraft = defineModel<Record<string, number>>({ required: true });

const props = defineProps<{
  canShow: boolean;
  hasChanges: boolean;
  hasLoadedEveryUser: boolean;
  users: SplitUser[];
}>();

const emit = defineEmits<{
  apply: [];
  reset: [];
}>();
</script>

<template>
  <AppCard v-if="props.canShow" class="space-y-4 rounded-2xl p-4!">
    <AppPercentageSplitEditor v-model="splitDraft" :users="props.users" />

    <div
      class="flex flex-col gap-3 border-t pt-4 sm:flex-row sm:items-center sm:justify-between"
      :style="{ borderColor: 'var(--app-color-border)' }"
    >
      <div class="space-y-1">
        <AppText size="sm" tone="subtle">
          Esta barra ajusta los porcentajes de los usuarios cargados en pantalla.
        </AppText>
        <AppText v-if="!props.hasLoadedEveryUser" size="sm" tone="subtle">
          Carga el resto de usuarios para repartir el 100% sobre toda la cuenta antes de guardar.
        </AppText>
        <AppText v-else size="sm" tone="subtle">
          La persistencia final requiere un endpoint masivo para enviar toda la distribución.
        </AppText>
      </div>

      <div class="flex gap-2 self-end sm:self-auto">
        <AppButton variant="secondary" :disabled="!props.hasChanges" @click="emit('reset')">
          Restablecer
        </AppButton>
        <AppButton variant="primary" :disabled="!props.hasChanges" @click="emit('apply')">
          Aplicar
        </AppButton>
      </div>
    </div>
  </AppCard>
</template>
