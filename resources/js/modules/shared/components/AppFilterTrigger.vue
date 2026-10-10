<script setup lang="ts">
import { AdjustmentsHorizontalIcon } from '@heroicons/vue/24/outline';
import AppButton from './AppButton.vue';

const props = defineProps<{ count: number; open: boolean; controls: string }>();
const emit = defineEmits<{ click: [] }>();
</script>

<template>
    <AppButton
        variant="outline"
        class="shrink-0"
        :aria-label="props.count ? `Abrir filtros, ${props.count} activos` : 'Abrir filtros'"
        :aria-controls="props.controls"
        :aria-expanded="props.open"
        @click="emit('click')"
    >
        <AdjustmentsHorizontalIcon class="h-5 w-5" aria-hidden="true" />
        <span v-if="props.count" aria-hidden="true">{{ props.count }}</span>
    </AppButton>
</template>
