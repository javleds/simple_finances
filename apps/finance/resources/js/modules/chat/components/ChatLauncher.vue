<script setup lang="ts">
import { defineAsyncComponent, nextTick, ref } from 'vue';
import { ChatBubbleLeftRightIcon } from '@heroicons/vue/24/outline';
import AppIconButton from '@/modules/shared/components/AppIconButton.vue';
const ChatPanel = defineAsyncComponent(() => import('./ChatPanel.vue'));
const enabled =
    document.querySelector<HTMLMetaElement>('meta[name="chat-enabled"]')?.content === 'true';
const open = ref(false);
const loaded = ref(false);
function toggle(): void {
    loaded.value = true;
    open.value = !open.value;
}
async function close(): Promise<void> {
    open.value = false;
    await nextTick();
    document.getElementById('chat-trigger')?.focus();
}
</script>
<template>
    <AppIconButton
        id="chat-trigger"
        aria-label="Abrir chat"
        ariaLabel="Abrir chat"
        aria-haspopup="dialog"
        :aria-expanded="open"
        aria-controls="finance-chat"
        @click="toggle"
    >
        <ChatBubbleLeftRightIcon class="h-5 w-5" />
    </AppIconButton>
    <ChatPanel v-if="loaded" :open="open" :enabled="enabled" @close="close" />
</template>
