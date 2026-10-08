<script setup lang="ts">
import type { AppModalAction } from '@/modules/shared/types/modal';
import { AppModal, AppText } from '@/modules/shared/components';

const props = defineProps<{
  actions: ReadonlyArray<AppModalAction>;
  open: boolean;
}>();

const emit = defineEmits<{
  close: [];
}>();
</script>

<template>
  <AppModal
    :open="props.open"
    :actions="props.actions"
    title="Agregar usuario"
    variant="default"
    @close="emit('close')"
  >
    <div class="space-y-3">
      <AppText>
        La API permite adjuntar un usuario existente por `user_id`, pero este frontend todavía no
        cuenta con un catálogo global de usuarios para seleccionarlo desde aquí.
      </AppText>
      <AppText size="sm" tone="subtle">
        Por ahora, la incorporación de nuevos miembros sigue el flujo de invitaciones de la cuenta.
      </AppText>
    </div>
  </AppModal>
</template>
