<script setup lang="ts">
import {
    CheckCircleIcon,
    ExclamationCircleIcon,
    ExclamationTriangleIcon,
    InformationCircleIcon,
} from '@heroicons/vue/24/outline';
import { computed } from 'vue';
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
        presentation?: 'dialog' | 'sheet';
    }>(),
    {
        variant: 'default',
        actions: () => [],
        closeLabel: 'Cerrar',
        presentation: 'dialog',
    },
);

const emit = defineEmits<{
    close: [];
    action: [key: string];
}>();

const modalVariantStyles = {
    default: {
        headerClass:
            'bg-[color-mix(in_srgb,var(--app-color-primary)_10%,var(--app-color-surface))]',
        icon: InformationCircleIcon,
        iconStyle: {
            backgroundColor: 'color-mix(in srgb, var(--app-color-primary) 16%, transparent)',
            color: 'var(--app-color-primary)',
        },
    },
    warning: {
        headerClass:
            'bg-[color-mix(in_srgb,var(--app-color-warning)_10%,var(--app-color-surface))]',
        icon: ExclamationTriangleIcon,
        iconStyle: {
            backgroundColor: 'color-mix(in srgb, var(--app-color-warning) 16%, transparent)',
            color: 'var(--app-color-warning)',
        },
    },
    danger: {
        headerClass: 'bg-[color-mix(in_srgb,var(--app-color-danger)_10%,var(--app-color-surface))]',
        icon: ExclamationCircleIcon,
        iconStyle: {
            backgroundColor: 'color-mix(in srgb, var(--app-color-danger) 16%, transparent)',
            color: 'var(--app-color-danger)',
        },
    },
    success: {
        headerClass:
            'bg-[color-mix(in_srgb,var(--app-color-success)_10%,var(--app-color-surface))]',
        icon: CheckCircleIcon,
        iconStyle: {
            backgroundColor: 'color-mix(in srgb, var(--app-color-success) 16%, transparent)',
            color: 'var(--app-color-success)',
        },
    },
} as const;

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
            tone: 'danger',
            autoClose: true,
        },
    ];
});

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
        :visible="props.open"
        :header="props.title"
        :aria-label="props.title"
        modal
        dismissable-mask
        block-scroll
        :draggable="false"
        :close-button-props="{ 'aria-label': 'Cerrar modal' }"
        :class="
            props.presentation === 'sheet'
                ? 'm-0! max-h-[90dvh] overflow-hidden w-full max-w-xl rounded-t-3xl! rounded-b-none! sm:m-4! sm:w-[calc(100%-2rem)] sm:rounded-3xl!'
                : 'w-[calc(100%-2rem)] max-w-xl'
        "
        :pt="{
            mask: {
                class: props.presentation === 'sheet' ? 'items-end! sm:items-center!' : undefined,
            },
            header: {
                class:
                    props.presentation === 'sheet'
                        ? 'bg-(--app-color-surface)'
                        : modalStyle.headerClass,
            },
            content: { class: 'min-h-0 overflow-y-auto' },
            footer: { class: 'flex border-t border-(--app-color-border) pt-4' },
        }"
        @update:visible="!$event && closeModal()"
    >
        <template #header>
            <div class="flex min-w-0 flex-1 items-center gap-3">
                <div
                    v-if="props.presentation !== 'sheet'"
                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full"
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
        </template>
        <slot />
        <template #footer>
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
                    class="min-h-11 min-w-0 flex-1"
                    @click="handleAction(action)"
                >
                    <span
                        v-if="action.loading"
                        aria-hidden="true"
                        class="h-4 w-4 shrink-0 animate-spin rounded-full border-2 border-current border-t-transparent"
                    />
                    <component :is="action.icon" v-else-if="action.icon" class="h-5 w-5 shrink-0" />
                    <span>{{ action.label }}</span>
                </Button>
            </slot>
        </template>
    </Dialog>
</template>
