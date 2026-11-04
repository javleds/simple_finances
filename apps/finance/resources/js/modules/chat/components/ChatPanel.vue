<script setup lang="ts">
import { computed, nextTick, onMounted, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import AppModal from '@/modules/shared/components/AppModal.vue';
import AppButton from '@/modules/shared/components/AppButton.vue';
import AppText from '@/modules/shared/components/AppText.vue';
import AppLink from '@/modules/shared/components/AppLink.vue';
import AppSearchSelect from '@/modules/shared/components/AppSearchSelect.vue';
import ChatMessageContent from './ChatMessageContent.vue';
import { routeChatContext } from '../composables/context';
import { useAccountRelationshipAccount } from '@/modules/accounts/composables/useAccountRelationshipAccount';
import { useChat } from '../composables/useChat';
const props = defineProps<{ open: boolean }>();
defineEmits<{ close: [] }>();
const route = useRoute();
const context = computed(() => routeChatContext(route.name, route.params.accountId));
const { account } = useAccountRelationshipAccount(computed(() => context.value.account_id ?? ''));
const contextLabel = computed(() =>
    context.value.account_id
        ? `Contexto: ${account.value?.name ?? `cuenta #${context.value.account_id}`}`
        : 'Contexto: todas tus cuentas',
);
const chat = useChat();
const {
    threads,
    thread,
    messages,
    run,
    busy,
    error,
    status,
    partial,
    historyCursor,
    threadsCursor,
} = chat;
const draft = ref('');
const input = ref<HTMLTextAreaElement | null>(null);
const transcript = ref<HTMLElement | null>(null);
const nearBottom = ref(true);
const options = computed(() =>
    threads.value.map((item) => ({
        label: `Conversación ${new Date(item.created_at).toLocaleString('es-MX')}`,
        value: item.id,
    })),
);
onMounted(() => {
    void chat.refreshThreads();
});
watch(
    () => props.open,
    async (open) => {
        if (open) {
            await nextTick();
            input.value?.focus();
        }
    },
    { immediate: true },
);
watch(
    () => messages.value.length,
    async () => {
        if (nearBottom.value) {
            await nextTick();
            transcript.value?.scrollTo({ top: transcript.value.scrollHeight });
        }
    },
);
function trackScroll(): void {
    const el = transcript.value;
    if (el) nearBottom.value = el.scrollHeight - el.scrollTop - el.clientHeight < 80;
}
async function send(): Promise<void> {
    if (draft.value.length > 4000) return;
    const sent = await chat.send(draft.value, context.value);
    if (sent) draft.value = '';
}
function keydown(event: KeyboardEvent): void {
    if (event.key === 'Enter' && !event.shiftKey && !event.isComposing) {
        event.preventDefault();
        void send();
    }
}
</script>
<template>
    <AppModal
        :open="open"
        title="Asistente financiero"
        presentation="fullscreen"
        @close="$emit('close')"
    >
        <div class="flex h-[55dvh] min-h-64 flex-col gap-3">
            <div class="flex items-end gap-2">
                <div class="min-w-0 flex-1">
                    <AppSearchSelect
                        id="chat-conversation"
                        :model-value="thread"
                        :options="options"
                        label="Conversación"
                        class="w-full min-w-0"
                        :disabled="busy"
                        @update:model-value="$event && chat.select(String($event))"
                    />
                </div>
                <AppButton
                    class="shrink-0"
                    variant="ghost"
                    :disabled="busy"
                    @click="chat.newConversation"
                    >Nueva</AppButton
                >
            </div>
            <AppButton v-if="threadsCursor" variant="ghost" @click="chat.refreshThreads(true)"
                >Más conversaciones</AppButton
            >
            <AppText size="sm">{{ contextLabel }}. Puedes preguntar por otra cuenta.</AppText>
            <div
                ref="transcript"
                class="min-h-0 flex-1 space-y-4 overflow-y-auto"
                role="log"
                aria-label="Mensajes"
                aria-live="polite"
                aria-relevant="additions"
                @scroll="trackScroll"
            >
                <AppButton v-if="historyCursor" variant="ghost" @click="chat.history(true)"
                    >Mensajes anteriores</AppButton
                >
                <AppText v-if="!messages.length"
                    >Pregunta por tus balances, pendientes o gastos. El asistente solo consulta
                    información.</AppText
                >
                <article
                    v-for="message in messages"
                    :key="message.id"
                    class="rounded-xl p-3"
                    :class="
                        message.role === 'user'
                            ? 'ml-6 bg-(--app-color-surface-muted)'
                            : 'mr-6 border border-(--app-color-border)'
                    "
                >
                    <AppText size="sm">{{ message.role === 'user' ? 'Tú' : 'Asistente' }}</AppText>
                    <ChatMessageContent :content="message.content" />
                    <AppText v-for="warning in message.warnings" :key="warning" size="sm">{{
                        warning
                    }}</AppText>
                    <div v-if="message.references.length" class="mt-2 flex flex-wrap gap-2">
                        <AppLink
                            v-for="reference in message.references"
                            :key="String(reference.account_id)"
                            :to="{
                                name: 'admin.accounts.transactions',
                                params: { accountId: String(reference.account_id) },
                            }"
                            >Cuenta #{{ reference.account_id }}</AppLink
                        >
                    </div>
                </article>
            </div>
            <div v-if="partial" aria-live="off">
                <AppText size="sm">Respuesta en curso</AppText
                ><ChatMessageContent :content="partial" />
            </div>
            <AppText v-if="run || busy" role="status">{{
                status || 'Preparando respuesta…'
            }}</AppText>
            <div v-if="error" role="alert">
                <AppText>{{ error }}</AppText
                ><AppButton variant="ghost" @click="chat.recover">Reintentar / recuperar</AppButton>
            </div>
        </div>
        <template #footer>
            <form class="w-full space-y-2" @submit.prevent="send">
                <label for="assistant-message" class="text-sm">Tu pregunta</label>
                <textarea
                    id="assistant-message"
                    ref="input"
                    v-model="draft"
                    rows="2"
                    maxlength="4000"
                    class="w-full resize-none rounded-lg border border-(--app-color-border) bg-(--app-color-surface) p-3 text-(--app-color-text)"
                    :disabled="busy"
                    @keydown="keydown"
                />
                <div class="flex justify-end gap-2">
                    <AppButton v-if="run" variant="ghost" @click="chat.cancel">Detener</AppButton
                    ><AppButton type="submit" :disabled="busy || !!run || !draft.trim()"
                        >Enviar</AppButton
                    >
                </div>
            </form>
        </template>
    </AppModal>
</template>
