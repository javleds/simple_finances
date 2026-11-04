<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import Galleria from 'primevue/galleria';
import { ArrowDownTrayIcon, ArrowPathIcon, MinusIcon, PlusIcon } from '@heroicons/vue/24/outline';
import { AppButton, AppModal } from '@/modules/shared/components';

const props = defineProps<{
    images: ReadonlyArray<{ id: string; name: string; url: string; failed?: boolean }>;
    activeId: string | null;
}>();
const emit = defineEmits<{
    close: [];
    select: [id: string];
    download: [id: string];
    retry: [id: string];
}>();
const stage = ref<HTMLElement | null>(null);
const displayedImage = ref<HTMLImageElement | null>(null);
let resizeObserver: ResizeObserver | null = null;
const zoom = ref(1);
const offsetX = ref(0);
const offsetY = ref(0);
const dragging = ref(false);
const activeIndex = computed(() => props.images.findIndex((image) => image.id === props.activeId));
const image = computed(() => props.images[activeIndex.value]);
const galleryImages = computed(() => [...props.images]);
const galleryIndex = computed({
    get: () => Math.max(0, activeIndex.value),
    set: (index: number) => {
        const next = props.images[index];
        if (next && next.id !== props.activeId) emit('select', next.id);
    },
});
watch(image, (next, previous) => {
    if (!next && previous && props.activeId) emit('close');
});
const pointers = new Map<number, { x: number; y: number }>();
let start: { x: number; y: number; offsetX: number; offsetY: number } | null = null;
let pinch: {
    distance: number;
    zoom: number;
    centerX: number;
    centerY: number;
    offsetX: number;
    offsetY: number;
} | null = null;
let swiping = false;

function reset(): void {
    zoom.value = 1;
    offsetX.value = 0;
    offsetY.value = 0;
    dragging.value = false;
    pointers.clear();
    start = null;
    pinch = null;
    swiping = false;
}
watch(() => props.activeId, reset);
function clampOffsets(): void {
    const bounds = stage.value?.getBoundingClientRect();
    const baseWidth = displayedImage.value?.clientWidth ?? 0;
    const baseHeight = displayedImage.value?.clientHeight ?? 0;
    const maxX = Math.max(0, (baseWidth * zoom.value - (bounds?.width ?? 0)) / 2);
    const maxY = Math.max(0, (baseHeight * zoom.value - (bounds?.height ?? 0)) / 2);
    offsetX.value = Math.max(-maxX, Math.min(maxX, offsetX.value));
    offsetY.value = Math.max(-maxY, Math.min(maxY, offsetY.value));
}
function changeZoom(value: number): void {
    zoom.value = Math.max(1, Math.min(4, value));
    clampOffsets();
}
function navigate(direction: -1 | 1): void {
    const nextImage = props.images[activeIndex.value + direction];
    if (image.value && nextImage) emit('select', nextImage.id);
}
function keyboard(event: KeyboardEvent): void {
    if (!image.value) return;
    if (event.key === 'Escape') {
        event.preventDefault();
        event.stopImmediatePropagation();
        emit('close');
        return;
    }
    if (event.altKey || event.ctrlKey || event.metaKey) return;
    if (
        event.target instanceof HTMLElement &&
        event.target.matches('input,textarea,select,[contenteditable="true"]')
    )
        return;
    if (event.key === 'ArrowLeft' || event.key === 'ArrowRight') {
        event.preventDefault();
        navigate(event.key === 'ArrowLeft' ? -1 : 1);
    }
}
onMounted(() => window.addEventListener('keydown', keyboard, true));
watch(
    stage,
    (element) => {
        resizeObserver?.disconnect();
        if (!element || typeof ResizeObserver === 'undefined') return;
        resizeObserver = new ResizeObserver(clampOffsets);
        resizeObserver.observe(element);
    },
    { flush: 'post' },
);
onBeforeUnmount(() => {
    window.removeEventListener('keydown', keyboard, true);
    resizeObserver?.disconnect();
});
function pinchGeometry(): { distance: number; centerX: number; centerY: number } | null {
    const [first, second] = [...pointers.values()];
    if (!first || !second) return null;
    return {
        distance: Math.hypot(second.x - first.x, second.y - first.y),
        centerX: (first.x + second.x) / 2,
        centerY: (first.y + second.y) / 2,
    };
}
function pointerDown(event: PointerEvent): void {
    if (event.button !== 0 || pointers.size >= 2) return;
    pointers.set(event.pointerId, { x: event.clientX, y: event.clientY });
    stage.value?.setPointerCapture?.(event.pointerId);
    dragging.value = true;
    if (pointers.size === 1) {
        start = {
            x: event.clientX,
            y: event.clientY,
            offsetX: offsetX.value,
            offsetY: offsetY.value,
        };
        swiping = zoom.value === 1;
    }
    if (pointers.size === 2) {
        const geometry = pinchGeometry();
        if (geometry)
            pinch = {
                ...geometry,
                zoom: zoom.value,
                offsetX: offsetX.value,
                offsetY: offsetY.value,
            };
        swiping = false;
    }
}
function pointerMove(event: PointerEvent): void {
    if (!pointers.has(event.pointerId)) return;
    pointers.set(event.pointerId, { x: event.clientX, y: event.clientY });
    const geometry = pinchGeometry();
    if (geometry && pinch && pinch.distance > 0) {
        zoom.value = Math.max(1, Math.min(4, (pinch.zoom * geometry.distance) / pinch.distance));
        offsetX.value = pinch.offsetX + geometry.centerX - pinch.centerX;
        offsetY.value = pinch.offsetY + geometry.centerY - pinch.centerY;
        clampOffsets();
        return;
    }
    if (start && zoom.value > 1) {
        offsetX.value = start.offsetX + event.clientX - start.x;
        offsetY.value = start.offsetY + event.clientY - start.y;
        clampOffsets();
    }
}
function pointerUp(event: PointerEvent): void {
    if (!pointers.has(event.pointerId)) return;
    const wasSingle = pointers.size === 1;
    pointers.delete(event.pointerId);
    if (event.type !== 'pointercancel' && wasSingle && swiping && start && zoom.value === 1) {
        const horizontal = event.clientX - start.x;
        const vertical = event.clientY - start.y;
        if (Math.abs(horizontal) >= 50 && Math.abs(horizontal) > Math.abs(vertical))
            navigate(horizontal < 0 ? 1 : -1);
    }
    pinch = null;
    swiping = false;
    const remaining = [...pointers.values()][0];
    start = remaining ? { ...remaining, offsetX: offsetX.value, offsetY: offsetY.value } : null;
    dragging.value = pointers.size > 0;
}
</script>

<template>
    <AppModal
        :open="Boolean(image)"
        :title="image?.name ?? 'Imagen del comprobante'"
        presentation="fullscreen"
        size="wide"
        @close="emit('close')"
    >
        <div v-if="image" class="space-y-3">
            <div
                class="flex flex-wrap items-center justify-center gap-2"
                role="toolbar"
                aria-label="Controles de imagen"
            >
                <span class="text-sm tabular-nums" aria-live="polite"
                    >{{ activeIndex + 1 }} / {{ images.length }}</span
                >
                <AppButton
                    variant="ghost"
                    class="min-h-11 min-w-11"
                    aria-label="Reducir imagen"
                    :disabled="zoom <= 1"
                    @click="changeZoom(zoom - 0.5)"
                    ><MinusIcon class="h-5 w-5" aria-hidden="true"
                /></AppButton>
                <span
                    class="min-w-12 text-center text-sm tabular-nums"
                    role="status"
                    aria-label="Nivel de zoom"
                    >{{ Math.round(zoom * 100) }}%</span
                >
                <AppButton
                    variant="ghost"
                    class="min-h-11 min-w-11"
                    aria-label="Ampliar imagen"
                    :disabled="zoom >= 4"
                    @click="changeZoom(zoom + 0.5)"
                    ><PlusIcon class="h-5 w-5" aria-hidden="true"
                /></AppButton>
                <AppButton
                    variant="ghost"
                    class="min-h-11 min-w-11"
                    aria-label="Restablecer imagen"
                    @click="reset"
                    ><ArrowPathIcon class="h-5 w-5" aria-hidden="true"
                /></AppButton>
                <AppButton
                    variant="ghost"
                    class="min-h-11 min-w-11"
                    aria-label="Descargar imagen"
                    @click="emit('download', image.id)"
                    ><ArrowDownTrayIcon class="h-5 w-5" aria-hidden="true"
                /></AppButton>
            </div>
            <Galleria
                v-model:active-index="galleryIndex"
                :value="galleryImages"
                :show-thumbnails="false"
                :show-item-navigators="true"
                :circular="false"
                :pt="{
                    item: { class: 'w-full' },
                    items: { class: 'w-full' },
                    prevButton: {
                        'aria-label': 'Imagen anterior',
                        class: 'min-h-11! min-w-11! bg-black/60! text-white! hover:bg-black/80! focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-(--app-color-primary)',
                    },
                    nextButton: {
                        'aria-label': 'Imagen siguiente',
                        class: 'min-h-11! min-w-11! bg-black/60! text-white! hover:bg-black/80! focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-(--app-color-primary)',
                    },
                }"
            >
                <template #item="{ item }">
                    <div
                        ref="stage"
                        class="relative flex h-[60dvh] min-h-60 w-full touch-none items-center justify-center overflow-hidden rounded-(--app-radius-control) bg-black/10 select-none sm:h-[calc(90dvh-18rem)]"
                        :class="zoom > 1 ? (dragging ? 'cursor-grabbing' : 'cursor-grab') : ''"
                        aria-label="Imagen del comprobante"
                        @pointerdown.prevent="pointerDown"
                        @pointermove.prevent="pointerMove"
                        @pointerup="pointerUp"
                        @pointercancel="pointerUp"
                    >
                        <img
                            ref="displayedImage"
                            @load="clampOffsets"
                            v-if="item.url"
                            :src="item.url"
                            :alt="item.name"
                            draggable="false"
                            class="pointer-events-none max-h-full max-w-full object-contain will-change-transform"
                            :style="{
                                transform: `translate(${offsetX}px, ${offsetY}px) scale(${zoom})`,
                            }"
                        />
                        <div
                            v-if="!item.url && item.failed"
                            class="space-y-3 p-4 text-center"
                            @pointerdown.stop
                        >
                            <p role="alert">No fue posible cargar esta imagen.</p>
                            <AppButton variant="outline" @click="emit('retry', item.id)"
                                >Reintentar imagen</AppButton
                            >
                        </div>
                        <p v-else-if="!item.url" role="status">Cargando imagen…</p>
                    </div>
                </template>
            </Galleria>
            <p class="text-center text-xs text-(--app-color-text-subtle)">
                Amplía para ver detalles y arrastra para moverte. Desliza a los lados sin zoom para
                cambiar de imagen.
            </p>
        </div>
        <template #footer
            ><AppButton variant="ghost" full-width @click="emit('close')"
                >Cerrar visor</AppButton
            ></template
        >
    </AppModal>
</template>
