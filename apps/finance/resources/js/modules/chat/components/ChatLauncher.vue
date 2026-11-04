<script setup lang="ts">
import { defineAsyncComponent, ref } from 'vue';
import { ChatBubbleLeftRightIcon } from '@heroicons/vue/24/outline';
import AppIconButton from '@/modules/shared/components/AppIconButton.vue';
const ChatPanel = defineAsyncComponent(() => import('./ChatPanel.vue'));
const enabled =
    document.querySelector<HTMLMetaElement>('meta[name="chat-enabled"]')?.content === 'true';
const open = ref(false);
const loaded = ref(false);
function show(): void {
    loaded.value = true;
    open.value = true;
}
</script>
<template>
    <div
        v-if="enabled"
        class="fixed right-4 bottom-[calc(var(--app-bottom-nav-height)+env(safe-area-inset-bottom)+1rem)] z-30 lg:right-8 lg:bottom-8"
    >
        <AppIconButton
            :ariaLabel="'Abrir asistente'"
            aria-haspopup="dialog"
            :aria-expanded="open"
            class="h-14! w-14! rounded-full! bg-(--app-color-surface)! shadow-lg"
            @click="show"
            ><ChatBubbleLeftRightIcon class="h-6 w-6"
        /></AppIconButton>
    </div>
    <ChatPanel v-if="loaded" :open="open" @close="open = false" />
</template>
