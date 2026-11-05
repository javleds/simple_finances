<script setup lang="ts">
import { TrashIcon } from '@heroicons/vue/24/outline';
import AppIconButton from '@/modules/shared/components/AppIconButton.vue';
import AppButton from '@/modules/shared/components/AppButton.vue';
import AppEmptyState from '@/modules/shared/components/AppEmptyState.vue';
import AppListState from '@/modules/shared/components/AppListState.vue';
import AppLoadMoreFooter from '@/modules/shared/components/AppLoadMoreFooter.vue';
import type { ChatThread } from '../repositories/chatRepository';
defineProps<{
    threads: ChatThread[];
    selected: string | null;
    loading: boolean;
    error: string;
    hasMore: boolean;
    disabled?: boolean;
}>();
defineEmits<{ select: [id: string]; remove: [thread: ChatThread]; retry: []; more: [] }>();
function date(value: string): string {
    return new Date(value).toLocaleString('es-MX', { dateStyle: 'medium', timeStyle: 'short' });
}
</script>
<template>
    <div class="h-full overflow-y-auto" aria-label="Conversaciones anteriores">
        <AppListState
            :is-loading="loading"
            :has-items="threads.length > 0"
            :error="error"
            loading-label="Cargando conversaciones…"
            @retry="$emit('retry')"
        >
            <AppEmptyState
                v-if="!threads.length"
                message="Aún no tienes conversaciones. Crea un nuevo chat para comenzar."
            />
            <ul v-else class="space-y-3">
                <li v-for="item in threads" :key="item.id" class="flex items-center gap-2">
                    <AppButton
                        variant="outline"
                        class="h-auto! min-w-0 flex-1 justify-start! px-3! py-3! text-left!"
                        :disabled="disabled"
                        :aria-current="item.id === selected ? 'true' : undefined"
                        :class="
                            item.id === selected
                                ? 'border-(--app-color-primary)! bg-(--app-color-surface-muted)!'
                                : ''
                        "
                        @click="$emit('select', item.id)"
                    >
                        <span class="flex min-w-0 flex-1 flex-col gap-1">
                            <span class="line-clamp-2 font-semibold">{{
                                item.title || `Conversación del ${date(item.created_at)}`
                            }}</span>
                            <span
                                class="text-xs font-normal break-all text-(--app-color-text-muted)"
                                >ID: {{ item.id }}</span
                            >
                            <span class="text-xs font-normal text-(--app-color-text-muted)">{{
                                date(item.created_at)
                            }}</span>
                        </span>
                    </AppButton>
                    <AppIconButton
                        :ariaLabel="`Eliminar conversación ${item.title || item.id}`"
                        :disabled="disabled"
                        @click="$emit('remove', item)"
                    >
                        <TrashIcon class="h-5 w-5" />
                    </AppIconButton>
                </li>
            </ul>
            <AppLoadMoreFooter
                v-if="loading || error"
                :label="error || 'Cargando conversaciones…'"
                :show-retry="!!error"
                @retry="$emit('retry')"
            />
            <AppButton
                v-else-if="hasMore"
                class="mt-3 w-full"
                variant="ghost"
                @click="$emit('more')"
                >Más conversaciones</AppButton
            >
        </AppListState>
    </div>
</template>
