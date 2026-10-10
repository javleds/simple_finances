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
        presentation="sheet"
        :open="props.open"
        :actions="props.actions"
        title="Agregar usuario"
        variant="default"
        @close="emit('close')"
    >
        <div class="space-y-3">
            <AppText>
                Es posible vincular usuarios existentes, pero todavía no hay un directorio de
                usuarios disponible para seleccionarlos desde aquí.
            </AppText>
            <AppText size="sm" tone="subtle">
                Por ahora, la incorporación de nuevos miembros sigue el flujo de invitaciones de la
                cuenta.
            </AppText>
        </div>
    </AppModal>
</template>
