<script setup lang="ts">
import {
    CheckCircleIcon,
    ExclamationCircleIcon,
    ExclamationTriangleIcon,
    InformationCircleIcon,
} from '@heroicons/vue/24/outline';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import Dialog from 'primevue/dialog';
import Button from 'primevue/button';

import AppTitle from './AppTitle.vue';
import type { AppModalAction, AppModalVariant } from '@/modules/shared/types/modal';

const props = withDefaults(
    defineProps<{
        open: boolean;
        title: string;
        variant?: AppModalVariant;
        actions?: ReadonlyArray<AppModalAction>;
        closeLabel?: string;
        presentation?: 'dialog' | 'sheet' | 'fullscreen' | 'chat';
        showFooter?: boolean;
        size?: 'default' | 'wide';
    }>(),
    {
        variant: 'default',
        actions: () => [],
        closeLabel: 'Cerrar',
        presentation: 'dialog',
        size: 'default',
        showFooter: true,
    },
);

const emit = defineEmits<{
    close: [];
    action: [key: string];
}>();

const modalVariantStyles = {
    default: {
        icon: InformationCircleIcon,
        iconStyle: {
            backgroundColor: 'color-mix(in srgb, var(--app-color-primary) 16%, transparent)',
            color: 'var(--app-color-primary)',
        },
    },
    warning: {
        icon: ExclamationTriangleIcon,
        iconStyle: {
            backgroundColor: 'color-mix(in srgb, var(--app-color-warning) 16%, transparent)',
            color: 'var(--app-color-warning)',
        },
    },
    danger: {
        icon: ExclamationCircleIcon,
        iconStyle: {
            backgroundColor: 'color-mix(in srgb, var(--app-color-danger) 16%, transparent)',
            color: 'var(--app-color-danger)',
        },
    },
    success: {
        icon: CheckCircleIcon,
        iconStyle: {
            backgroundColor: 'color-mix(in srgb, var(--app-color-success) 16%, transparent)',
            color: 'var(--app-color-success)',
        },
    },
} as const;

const desktop = ref(false);
const viewportHeight = ref<number>();
const viewportTop = ref(0);
let media: MediaQueryList | undefined;
function updateViewport(): void {
    desktop.value = media?.matches ?? false;
    viewportHeight.value = window.visualViewport?.height ?? window.innerHeight;
    viewportTop.value = window.visualViewport?.offsetTop ?? 0;
}
onMounted(() => {
    if (props.presentation !== 'chat') return;
    media = window.matchMedia('(min-width: 1024px)');
    updateViewport();
    media.addEventListener('change', updateViewport);
    window.visualViewport?.addEventListener('resize', updateViewport);
    window.visualViewport?.addEventListener('scroll', updateViewport);
    window.addEventListener('resize', updateViewport);
});
onBeforeUnmount(() => {
    media?.removeEventListener('change', updateViewport);
    window.visualViewport?.removeEventListener('resize', updateViewport);
    window.visualViewport?.removeEventListener('scroll', updateViewport);
    window.removeEventListener('resize', updateViewport);
});
const isChat = computed(() => props.presentation === 'chat');
const isModal = computed(() => !isChat.value || !desktop.value);
const chatViewportStyle = computed(() =>
    isChat.value && !desktop.value
        ? {
              height: `${viewportHeight.value ?? window.innerHeight}px`,
              top: `${viewportTop.value}px`,
          }
        : undefined,
);

const modalStyle = computed(() => modalVariantStyles[props.variant]);

const resolvedActions = computed<ReadonlyArray<AppModalAction>>(() => {
    if (props.actions.length > 0) {
        const limitedActions = props.actions.slice(0, 3);
        const closeActionIndex = limitedActions.findIndex((action) => isCloseAction(action));

        if (closeActionIndex <= 0) {
            return limitedActions;
        }

        const closeAction = limitedActions[closeActionIndex];

        if (!closeAction) {
            return limitedActions;
        }

        const remainingActions = limitedActions.filter((_, index) => index !== closeActionIndex);

        return [closeAction, ...remainingActions];
    }

    return [
        {
            key: 'close',
            label: props.closeLabel,
            tone: 'neutral',
            autoClose: true,
        },
    ];
});

const presentationClasses = computed(() => {
    if (props.presentation === 'chat') return 'app-modal-chat';
    const desktopWidth = props.size === 'wide' ? 'sm:max-w-5xl!' : 'sm:max-w-xl!';
    const dialogWidth = props.size === 'wide' ? 'max-w-5xl' : 'max-w-xl';
    if (props.presentation === 'fullscreen') {
        return `m-0! h-dvh max-h-dvh! w-screen max-w-none! rounded-none! border-0! sm:m-4! sm:h-auto sm:max-h-[90dvh]! sm:w-[calc(100%-2rem)] ${desktopWidth} sm:rounded-(--app-radius-overlay)! sm:border!`;
    }

    if (props.presentation === 'sheet') {
        return `m-0! max-h-[90dvh]! w-full ${dialogWidth} rounded-t-(--app-radius-overlay)! rounded-b-none! sm:m-4! sm:w-[calc(100%-2rem)] sm:rounded-(--app-radius-overlay)!`;
    }

    return `m-4! max-h-[calc(100dvh-2rem)]! w-[calc(100%-2rem)] ${dialogWidth} rounded-(--app-radius-overlay)!`;
});

function onEscape(event: KeyboardEvent): void {
    if (!isChat.value || event.isComposing) return;
    event.stopPropagation();
    closeModal();
}

function closeModal(): void {
    emit('close');
}

function handleAction(action: AppModalAction): void {
    if (action.disabled || action.loading) {
        return;
    }

    emit('action', action.key);

    if (action.autoClose) {
        closeModal();
    }
}

function isCloseAction(action: AppModalAction): boolean {
    const normalizedKey = action.key.trim().toLowerCase();

    if (normalizedKey === 'close' || normalizedKey === 'cancel') {
        return true;
    }

    return action.autoClose === true;
}
</script>

<template>
    <Dialog
        :key="isChat ? (desktop ? 'chat-desktop' : 'chat-mobile') : 'modal'"
        :visible="props.open"
        :header="props.title"
        :aria-label="props.title"
        :modal="isModal"
        :dismissable-mask="!isChat"
        :block-scroll="isModal"
        :position="isChat ? 'bottomright' : 'center'"
        :close-on-escape="!isChat"
        @keydown.esc="onEscape"
        :draggable="false"
        :close-button-props="{
            'aria-label': isChat ? 'Cerrar chat' : 'Cerrar modal',
            severity: 'secondary',
            text: true,
            class: 'min-h-11 min-w-11 shrink-0',
        }"
        class="app-modal flex min-w-0 flex-col overflow-hidden"
        :class="presentationClasses"
        :pt="{
            mask: {
                class: props.presentation === 'sheet' ? 'items-end! sm:items-center!' : undefined,
                style: chatViewportStyle,
            },
            header: {
                class: 'shrink-0 border-b border-(--app-color-border) px-4 py-3',
                style:
                    props.presentation === 'fullscreen' || (isChat && !desktop)
                        ? { paddingTop: 'max(0.75rem, env(safe-area-inset-top))' }
                        : undefined,
            },
            content: {
                class: isChat
                    ? 'min-h-0 flex-1 overflow-hidden p-4'
                    : 'min-h-0 flex-1 overflow-x-hidden overflow-y-auto p-4',
            },
            footer: {
                class: 'flex shrink-0 gap-3 border-t border-(--app-color-border) p-4',
                style: { paddingBottom: 'max(1rem, env(safe-area-inset-bottom))' },
            },
        }"
        @update:visible="!$event && closeModal()"
    >
        <template #header>
            <slot name="header">
                <div class="flex min-w-0 flex-1 items-center gap-3">
                    <div
                        v-if="props.presentation !== 'sheet'"
                        class="flex h-6 w-6 shrink-0 items-center justify-center rounded-(--app-radius-control)"
                        :style="modalStyle.iconStyle"
                    >
                        <component :is="modalStyle.icon" class="h-5 w-5" />
                    </div>
                    <AppTitle
                        as="h2"
                        size="sm"
                        class="min-w-0 flex-1 text-left text-base! sm:text-lg!"
                        >{{ props.title }}</AppTitle
                    >
                </div>
            </slot>
        </template>
        <slot />
        <template v-if="props.showFooter" #footer>
            <slot name="footer">
                <Button
                    v-for="action in resolvedActions"
                    :key="action.key"
                    :type="action.type ?? 'button'"
                    :form="action.form"
                    :disabled="action.disabled || action.loading"
                    :aria-busy="action.loading ? 'true' : undefined"
                    :severity="
                        action.tone === 'danger'
                            ? 'danger'
                            : action.tone === 'neutral'
                              ? 'secondary'
                              : undefined
                    "
                    :text="action.tone === 'neutral'"
                    class="min-h-12 min-w-0 flex-1 text-base"
                    @click="handleAction(action)"
                >
                    <span
                        v-if="action.loading"
                        aria-hidden="true"
                        class="h-4 w-4 shrink-0 animate-spin rounded-full border-2 border-current border-t-transparent"
                    />
                    <component :is="action.icon" v-else-if="action.icon" class="h-5 w-5 shrink-0" />
                    <span class="wrap-anywhere">{{ action.label }}</span>
                </Button>
            </slot>
        </template>
    </Dialog>
</template>

<style>
.app-modal-chat {
    width: 100vw !important;
    max-width: none !important;
    height: 100% !important;
    max-height: 100% !important;
    margin: 0 !important;
    border-radius: 0 !important;
}
@media (min-width: 1024px) {
    .app-modal-chat {
        width: 440px !important;
        height: min(640px, calc(100dvh - var(--app-header-height) - 48px)) !important;
        max-width: calc(100vw - 48px) !important;
        margin: 24px !important;
        border-radius: var(--app-radius-overlay) !important;
    }
}
</style>
