<script setup lang="ts">
import Message from 'primevue/message';

const props = defineProps<{
    message: string;
    tone?: 'danger' | 'success' | 'info';
}>();
</script>

<template>
    <Message :severity="props.tone === 'danger' || !props.tone ? 'error' : props.tone">
        {{ props.message }}
    </Message>
</template>
