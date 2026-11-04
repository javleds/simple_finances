<script setup lang="ts">
import { computed, nextTick, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import { ArrowLeftIcon, Bars3Icon, PlusIcon } from '@heroicons/vue/24/outline';
import Textarea from 'primevue/textarea';
import AppModal from '@/modules/shared/components/AppModal.vue';
import AppButton from '@/modules/shared/components/AppButton.vue';
import AppIconButton from '@/modules/shared/components/AppIconButton.vue';
import AppText from '@/modules/shared/components/AppText.vue';
import AppLink from '@/modules/shared/components/AppLink.vue';
import AppEmptyState from '@/modules/shared/components/AppEmptyState.vue';
import AppListState from '@/modules/shared/components/AppListState.vue';
import ChatMessageContent from './ChatMessageContent.vue';
import ChatConversationList from './ChatConversationList.vue';
import { routeChatContext } from '../composables/context';
import { useAccountRelationshipAccount } from '@/modules/accounts/composables/useAccountRelationshipAccount';
import { useChat } from '../composables/useChat';

const props = defineProps<{ open: boolean; enabled: boolean }>();
defineEmits<{ close: [] }>();
const route = useRoute();
const context = computed(() => routeChatContext(route.name, route.params.accountId));
const { account } = useAccountRelationshipAccount(
    computed(() => (props.enabled ? (context.value.account_id ?? '') : '')),
);
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
    draft,
    creating,
    creationError,
    threadsLoading,
    threadsError,
    historyLoading,
    historyError,
} = chat;
const view = ref<'conversation' | 'history'>('conversation');
const transcript = ref<HTMLElement | null>(null);
const nearBottom = ref(true);
const selected = computed(() => threads.value.find((item) => item.id === thread.value));
const title = computed(() => {
    if (view.value === 'history') return 'Conversaciones';
    if (!thread.value) return 'Nuevo chat';
    if (selected.value?.title) return selected.value.title;
    if (!messages.value.length) return 'Nueva conversación';
    return selected.value
        ? `Conversación del ${new Date(selected.value.created_at).toLocaleDateString('es-MX')}`
        : 'Conversación';
});
async function focusInput(): Promise<void> {
    await nextTick();
    if (props.open && view.value === 'conversation')
        document.getElementById('assistant-message')?.focus();
}
watch(
    () => props.open,
    async (open) => {
        if (!props.enabled) return;
        if (!open) {
            chat.suspend();
            return;
        }
        void chat.refreshThreads();
        if (view.value === 'conversation') {
            await chat.resume();
            await focusInput();
        }
    },
    { immediate: true },
);
watch(
    () => messages.value.length,
    async () => {
        if (!nearBottom.value) return;
        await nextTick();
        transcript.value?.scrollTo({ top: transcript.value.scrollHeight });
    },
);
function trackScroll(): void {
    const el = transcript.value;
    if (el) nearBottom.value = el.scrollHeight - el.scrollTop - el.clientHeight < 80;
}
async function toggleHistory(): Promise<void> {
    if (view.value === 'conversation') {
        view.value = 'history';
        chat.suspend();
        await chat.refreshThreads();
        return;
    }
    view.value = 'conversation';
    await chat.resume();
    await focusInput();
}
async function select(id: string): Promise<void> {
    view.value = 'conversation';
    nearBottom.value = true;
    await chat.select(id);
    await focusInput();
}
async function create(): Promise<void> {
    if (!(await chat.newConversation())) return;
    view.value = 'conversation';
    nearBottom.value = true;
    await chat.resume();
    await focusInput();
}
async function send(): Promise<void> {
    if (draft.value.length > 4000 || !draft.value.trim()) return;
    nearBottom.value = true;
    await chat.send(draft.value, context.value);
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
        id="finance-chat"
        :open="open"
        :title="title"
        presentation="chat"
        :show-footer="enabled && view === 'conversation'"
        @close="$emit('close')"
    >
        <template #header>
            <div class="flex min-w-0 flex-1 items-center gap-2">
                <AppIconButton
                    v-if="enabled"
                    :aria-label="view === 'history' ? 'Volver a conversación' : 'Abrir historial'"
                    :ariaLabel="view === 'history' ? 'Volver a conversación' : 'Abrir historial'"
                    @click="toggleHistory"
                >
                    <ArrowLeftIcon v-if="view === 'history'" class="h-5 w-5" />
                    <Bars3Icon v-else class="h-5 w-5" />
                </AppIconButton>
                <AppIconButton
                    v-if="enabled"
                    ariaLabel="Nuevo chat"
                    :loading="creating"
                    :disabled="busy"
                    @click="create"
                >
                    <PlusIcon class="h-5 w-5" />
                </AppIconButton>
                <h2 class="min-w-0 flex-1 truncate text-sm font-semibold" :title="title">
                    {{ title }}
                </h2>
            </div>
        </template>
        <AppEmptyState v-if="!enabled" message="El asistente aún no está habilitado." />
        <div v-else class="flex h-full min-h-0 flex-col gap-3">
            <div v-if="creationError" class="shrink-0 space-y-2" role="alert">
                <AppText size="sm">{{ creationError }}</AppText>
                <AppButton variant="ghost" :loading="creating" @click="create"
                    >Reintentar nuevo chat</AppButton
                >
            </div>
            <ChatConversationList
                v-if="view === 'history'"
                class="min-h-0 flex-1"
                :threads="threads"
                :selected="thread"
                :loading="threadsLoading"
                :error="threadsError"
                :has-more="!!threadsCursor"
                @select="select"
                @retry="chat.refreshThreads()"
                @more="chat.refreshThreads(true)"
            />
            <template v-else>
                <div class="shrink-0 space-y-1">
                    <p v-if="thread" class="text-xs break-all text-(--app-color-text-muted)">
                        ID: {{ thread }}
                    </p>
                    <AppText size="sm"
                        >{{ contextLabel }}. Puedes preguntar por otra cuenta.</AppText
                    >
                </div>
                <div
                    ref="transcript"
                    class="min-h-0 flex-1 space-y-4 overflow-y-auto"
                    role="log"
                    aria-label="Mensajes"
                    aria-live="polite"
                    aria-relevant="additions"
                    @scroll="trackScroll"
                >
                    <AppListState
                        :is-loading="historyLoading"
                        :has-items="messages.length > 0"
                        :error="historyError"
                        loading-label="Cargando mensajes…"
                        @retry="chat.resume()"
                    >
                        <AppButton
                            v-if="historyCursor"
                            variant="ghost"
                            :loading="historyLoading"
                            @click="chat.history(true)"
                            >Mensajes anteriores</AppButton
                        >
                        <AppEmptyState
                            v-if="!messages.length"
                            message="Pregunta por tus balances, pendientes o gastos. El asistente solo consulta información."
                        />
                        <article
                            v-for="message in messages"
                            :key="message.id"
                            class="rounded-xl p-3 break-words"
                            :class="
                                message.role === 'user'
                                    ? 'ml-8 bg-(--app-color-surface-muted)'
                                    : 'mr-8 border border-(--app-color-border)'
                            "
                        >
                            <AppText size="sm">{{
                                message.role === 'user' ? 'Tú' : 'Asistente'
                            }}</AppText>
                            <ChatMessageContent :content="message.content" />
                            <AppText v-for="warning in message.warnings" :key="warning" size="sm">{{
                                warning
                            }}</AppText>
                            <div v-if="message.references.length" class="mt-2 flex flex-wrap gap-2">
                                <AppLink
                                    v-for="reference in message.references"
                                    :key="`${reference.account_id}-${reference.transaction_id ?? ''}`"
                                    :to="{
                                        name: 'admin.accounts.transactions',
                                        params: { accountId: String(reference.account_id) },
                                    }"
                                    >Cuenta #{{ reference.account_id }}</AppLink
                                >
                            </div>
                        </article>
                    </AppListState>
                </div>
                <div v-if="partial" class="max-h-28 shrink-0 overflow-y-auto" aria-live="off">
                    <AppText size="sm">Respuesta en curso</AppText
                    ><ChatMessageContent :content="partial" />
                </div>
                <AppText v-if="run || busy" role="status" size="sm">{{
                    status || 'Preparando respuesta…'
                }}</AppText>
                <div
                    v-if="error || (historyError && messages.length)"
                    class="shrink-0 space-y-2"
                    role="alert"
                >
                    <AppText size="sm">{{ error || historyError }}</AppText>
                    <AppButton variant="ghost" @click="chat.recover"
                        >Reintentar / recuperar</AppButton
                    >
                </div>
            </template>
        </div>
        <template #footer>
            <form class="w-full space-y-2" @submit.prevent="send">
                <label for="assistant-message" class="text-sm">Tu pregunta</label>
                <Textarea
                    id="assistant-message"
                    v-model="draft"
                    rows="2"
                    maxlength="4000"
                    fluid
                    class="max-h-32 resize-none"
                    :disabled="busy || creating"
                    @keydown="keydown"
                />
                <div class="flex justify-end gap-2">
                    <AppButton v-if="run" variant="ghost" @click="chat.cancel">Detener</AppButton>
                    <AppButton type="submit" :disabled="busy || creating || !!run || !draft.trim()"
                        >Enviar</AppButton
                    >
                </div>
            </form>
        </template>
    </AppModal>
</template>
