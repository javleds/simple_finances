<script setup lang="ts">
import Message from 'primevue/message';
import { AppButton, AppCard, AppPercentageSplitEditor, AppText } from '@/modules/shared/components';

type SplitUser = {
    id: string;
    name: string;
};

const splitDraft = defineModel<Record<string, number>>({ required: true });

const props = defineProps<{
    canShow: boolean;
    isSaving: boolean;
    saveError: string | null;
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
    <AppCard v-if="props.canShow" class="rounded-2xl p-4!">
        <div class="space-y-4">
            <fieldset :disabled="props.isSaving || !props.hasLoadedEveryUser" class="min-w-0">
                <AppPercentageSplitEditor v-model="splitDraft" :users="props.users" />
            </fieldset>
            <Message v-if="props.saveError" severity="error">{{ props.saveError }}</Message>

            <div
                class="flex flex-col gap-3 border-t pt-4 sm:flex-row sm:items-center sm:justify-between"
                :style="{ borderColor: 'var(--app-color-border)' }"
            >
                <div class="space-y-1">
                    <AppText size="sm" tone="subtle">
                        Ajusta la distribución predeterminada de los próximos movimientos.
                    </AppText>
                    <AppText v-if="!props.hasLoadedEveryUser" size="sm" tone="subtle">
                        Quita la búsqueda y carga todos los usuarios para ajustar la distribución.
                    </AppText>
                    <AppText v-else size="sm" tone="subtle">
                        Aplicar guarda los porcentajes; restablecer descarta los cambios pendientes.
                    </AppText>
                </div>

                <div class="flex gap-2 self-end sm:self-auto">
                    <AppButton variant="secondary" :disabled="props.isSaving || !props.hasChanges" @click="emit('reset')"> Restablecer </AppButton>
                    <AppButton variant="primary" :disabled="props.isSaving || !props.hasChanges || !props.hasLoadedEveryUser" @click="emit('apply')"> Aplicar </AppButton>
                </div>
            </div>
        </div>
    </AppCard>
</template>
