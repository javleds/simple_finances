<script setup lang="ts">
import AppButton from './AppButton.vue';
import AppModal from './AppModal.vue';

const props = withDefaults(
    defineProps<{
        open: boolean;
        id: string;
        applyDisabled?: boolean;
        clearLabel?: string;
    }>(),
    { applyDisabled: false, clearLabel: 'Limpiar filtros' },
);

const emit = defineEmits<{ apply: []; clear: []; close: [] }>();
</script>

<template>
    <AppModal
        :id="props.id"
        :open="props.open"
        title="Filtros"
        presentation="sheet"
        @close="emit('close')"
    >
        <form
            :id="`${props.id}-form`"
            class="grid gap-4 sm:gap-6"
            @submit.prevent="!props.applyDisabled && emit('apply')"
        >
            <slot />
        </form>
        <template #footer>
            <div class="flex w-full items-center justify-between gap-3">
                <AppButton variant="ghost" @click="emit('clear')">{{ props.clearLabel }}</AppButton>
                <AppButton type="submit" :form="`${props.id}-form`" :disabled="props.applyDisabled"
                    >Aplicar filtros</AppButton
                >
            </div>
        </template>
    </AppModal>
</template>
