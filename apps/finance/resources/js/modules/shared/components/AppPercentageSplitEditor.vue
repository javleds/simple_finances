<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch, useId } from 'vue';

import Button from 'primevue/button';

import AppInput from './AppInput.vue';
import AppText from './AppText.vue';

type SplitUser = {
    id: string;
    name: string;
    color?: string;
};

const TOTAL_BASIS_POINTS = 10_000;
const DEFAULT_STEP_PERCENTAGE = 5;
const HANDLE_WIDTH_PX = 44;
const USER_COLORS = ['#2563EB', '#0F766E', '#EA580C', '#7C3AED', '#D97706', '#DC2626'] as const;

const props = withDefaults(
    defineProps<{
        users: ReadonlyArray<SplitUser>;
        modelValue: Record<string, number>;
        step?: number;
        monetary?: {
            total: number;
            amount: number;
            maximum: number;
            source: string;
            recipient: string;
            sourceRemaining?: number;
        };
        disabled?: boolean;
    }>(),
    {
        step: DEFAULT_STEP_PERCENTAGE,
    },
);

const emit = defineEmits<{
    'update:modelValue': [value: Record<string, number>];
    'update:amount': [value: number];
}>();

const amountInputId = useId();
const amountDraft = ref('0.00');
const monetaryPercentage = computed(() =>
    props.monetary?.total ? (props.monetary.amount / props.monetary.total) * 100 : 0,
);
watch(
    () => props.monetary?.amount,
    (amount) => {
        amountDraft.value = (amount ?? 0).toFixed(2);
    },
    { immediate: true },
);

function updateAmount(value: number): void {
    if (!props.monetary || props.disabled) return;
    const cents = Math.max(
        0,
        Math.min(Math.round(props.monetary.maximum * 100), Math.round(value * 100)),
    );
    emit('update:amount', (Number.isFinite(cents) ? cents : 0) / 100);
    amountDraft.value = ((Number.isFinite(cents) ? cents : 0) / 100).toFixed(2);
}

function handleAmountKey(event: KeyboardEvent): void {
    if (!props.monetary || !['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) return;
    event.preventDefault();
    if (event.key === 'Home') return updateAmount(0);
    if (event.key === 'End') return updateAmount(props.monetary.maximum);
    updateAmount(props.monetary.amount + (event.key === 'ArrowRight' ? 0.01 : -0.01));
}

function formatMoney(amount: number): string {
    return new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(amount);
}

const trackRef = ref<HTMLElement | null>(null);
const draggingHandleIndex = ref<number | null>(null);
const activePointerId = ref<number | null>(null);
const activeHandleElement = ref<HTMLElement | null>(null);
const focusedInputUserId = ref<string | null>(null);
const percentageInputDrafts = ref<Record<string, string>>({});

const normalizedPercentages = computed(() => normalizePercentages(props.users, props.modelValue));
const stepBasisPoints = computed(() => normalizeStepBasisPoints(props.step));

const userItems = computed(() =>
    props.users.map((user, index) => ({
        ...user,
        color: user.color ?? USER_COLORS[index % USER_COLORS.length] ?? USER_COLORS[0],
        percentage: normalizedPercentages.value[user.id] ?? 0,
    })),
);

const cumulativeBoundaries = computed(() => {
    let runningTotal = 0;

    return userItems.value.map((user) => {
        runningTotal += user.percentage;
        return runningTotal;
    });
});

watch(
    normalizedPercentages,
    (nextValue) => {
        if (props.monetary || arePercentagesEqual(nextValue, props.modelValue)) {
            return;
        }

        emit('update:modelValue', nextValue);
    },
    { immediate: true },
);

watch(
    userItems,
    (nextUsers) => {
        percentageInputDrafts.value = nextUsers.reduce<Record<string, string>>((drafts, user) => {
            drafts[user.id] =
                focusedInputUserId.value === user.id
                    ? (percentageInputDrafts.value[user.id] ?? user.percentage.toFixed(2))
                    : user.percentage.toFixed(2);

            return drafts;
        }, {});
    },
    { immediate: true },
);

function normalizePercentages(
    users: ReadonlyArray<SplitUser>,
    percentages: Record<string, number>,
): Record<string, number> {
    if (users.length === 0) {
        return {};
    }

    const basisPointValues = users.map((user) => {
        const currentValue = percentages[user.id];
        const normalizedValue =
            typeof currentValue === 'number' && Number.isFinite(currentValue) ? currentValue : 0;

        return {
            userId: user.id,
            basisPoints: toBasisPoints(normalizedValue),
        };
    });

    const total = basisPointValues.reduce((sum, item) => sum + item.basisPoints, 0);

    if (total === TOTAL_BASIS_POINTS) {
        return basisPointsToRecord(basisPointValues);
    }

    if (total <= 0) {
        return basisPointsToRecord(
            distributeEvenly(
                users.map((user) => user.id),
                TOTAL_BASIS_POINTS,
            ),
        );
    }

    return basisPointsToRecord(
        distributeByWeight(
            basisPointValues.map((item) => ({ key: item.userId, weight: item.basisPoints })),
            TOTAL_BASIS_POINTS,
        ),
    );
}

function basisPointsToRecord(
    values: ReadonlyArray<{ userId: string; basisPoints: number }>,
): Record<string, number> {
    return values.reduce<Record<string, number>>((accumulator, item) => {
        accumulator[item.userId] = fromBasisPoints(item.basisPoints);
        return accumulator;
    }, {});
}

function distributeEvenly(
    userIds: ReadonlyArray<string>,
    totalBasisPoints: number,
): Array<{ userId: string; basisPoints: number }> {
    if (userIds.length === 0) {
        return [];
    }

    const baseValue = Math.floor(totalBasisPoints / userIds.length);
    let remainder = totalBasisPoints - baseValue * userIds.length;

    return userIds.map((userId) => {
        const extra = remainder > 0 ? 1 : 0;
        remainder = Math.max(0, remainder - extra);

        return {
            userId,
            basisPoints: baseValue + extra,
        };
    });
}

function distributeByWeight(
    items: ReadonlyArray<{ key: string; weight: number }>,
    totalBasisPoints: number,
): Array<{ userId: string; basisPoints: number }> {
    const sanitizedItems = items.map((item) => ({
        key: item.key,
        weight: Math.max(0, item.weight),
    }));
    const totalWeight = sanitizedItems.reduce((sum, item) => sum + item.weight, 0);

    if (sanitizedItems.length === 0) {
        return [];
    }

    if (totalWeight === 0) {
        return distributeEvenly(
            sanitizedItems.map((item) => item.key),
            totalBasisPoints,
        );
    }

    const distributed = sanitizedItems.map((item, index) => {
        const exactValue = (item.weight / totalWeight) * totalBasisPoints;
        const flooredValue = Math.floor(exactValue);

        return {
            userId: item.key,
            basisPoints: flooredValue,
            remainder: exactValue - flooredValue,
            index,
        };
    });

    let remainder = totalBasisPoints - distributed.reduce((sum, item) => sum + item.basisPoints, 0);

    distributed
        .slice()
        .sort((left, right) => {
            if (right.remainder === left.remainder) {
                return left.index - right.index;
            }

            return right.remainder - left.remainder;
        })
        .forEach((item) => {
            if (remainder <= 0) {
                return;
            }

            distributed[item.index]!.basisPoints += 1;
            remainder -= 1;
        });

    return distributed.map(({ userId, basisPoints }) => ({
        userId,
        basisPoints,
    }));
}

function toBasisPoints(value: number): number {
    if (!Number.isFinite(value)) {
        return 0;
    }

    return clampBasisPoints(Math.round(value * 100));
}

function fromBasisPoints(value: number): number {
    return Number((value / 100).toFixed(2));
}

function clampBasisPoints(value: number): number {
    return Math.min(TOTAL_BASIS_POINTS, Math.max(0, value));
}

function normalizeStepBasisPoints(step: number): number {
    const sanitizedStep = Number.isFinite(step) && step > 0 ? step : DEFAULT_STEP_PERCENTAGE;

    return Math.min(TOTAL_BASIS_POINTS, Math.max(1, Math.round(sanitizedStep * 100)));
}

function snapBasisPoints(value: number): number {
    const snappedValue = Math.round(value / stepBasisPoints.value) * stepBasisPoints.value;

    return clampBasisPoints(snappedValue);
}

function arePercentagesEqual(left: Record<string, number>, right: Record<string, number>): boolean {
    const allKeys = new Set([...Object.keys(left), ...Object.keys(right)]);

    return [...allKeys].every(
        (key) => toBasisPoints(left[key] ?? 0) === toBasisPoints(right[key] ?? 0),
    );
}

function emitBasisPointValues(
    values: ReadonlyArray<{ userId: string; basisPoints: number }>,
): void {
    emit('update:modelValue', basisPointsToRecord(values));
}

function inputValue(userId: string, percentage: number): string {
    return percentageInputDrafts.value[userId] ?? percentage.toFixed(2);
}

function handlePercentageInput(userId: string, nextValue: string): void {
    percentageInputDrafts.value = {
        ...percentageInputDrafts.value,
        [userId]: nextValue,
    };
}

function handlePercentageFocus(userId: string, percentage: number): void {
    focusedInputUserId.value = userId;
    percentageInputDrafts.value = {
        ...percentageInputDrafts.value,
        [userId]: percentageInputDrafts.value[userId] ?? percentage.toFixed(2),
    };
}

function handlePercentageBlur(userId: string): void {
    const draftValue = percentageInputDrafts.value[userId] ?? '';
    focusedInputUserId.value = null;
    updateUserPercentage(userId, draftValue);
}

function updateUserPercentage(userId: string, nextValue: string): void {
    const targetUserIndex = userItems.value.findIndex((user) => user.id === userId);

    if (targetUserIndex === -1) {
        return;
    }

    const requestedValue = Number.parseFloat(nextValue);
    const nextBasisPoints = clampBasisPoints(
        Number.isFinite(requestedValue) ? toBasisPoints(requestedValue) : 0,
    );
    const remainingBasisPoints = TOTAL_BASIS_POINTS - nextBasisPoints;
    const otherUsers = userItems.value.filter((user) => user.id !== userId);

    if (otherUsers.length === 0) {
        emitBasisPointValues([{ userId, basisPoints: TOTAL_BASIS_POINTS }]);
        return;
    }

    const redistributedOthers = distributeByWeight(
        otherUsers.map((user) => ({
            key: user.id,
            weight: toBasisPoints(user.percentage),
        })),
        remainingBasisPoints,
    );

    const nextValues = userItems.value.map((user) => {
        if (user.id === userId) {
            return {
                userId,
                basisPoints: nextBasisPoints,
            };
        }

        const redistributedValue = redistributedOthers.find((item) => item.userId === user.id);

        return {
            userId: user.id,
            basisPoints: redistributedValue?.basisPoints ?? 0,
        };
    });

    emitBasisPointValues(nextValues);
}

function startHandleDrag(handleIndex: number, event: PointerEvent): void {
    if (props.disabled) return;
    const currentTarget = event.currentTarget;

    if (!(currentTarget instanceof HTMLElement)) {
        return;
    }

    draggingHandleIndex.value = handleIndex;
    activePointerId.value = event.pointerId;
    activeHandleElement.value = currentTarget;
    currentTarget.setPointerCapture(event.pointerId);
    window.addEventListener('pointermove', handlePointerMove);
    window.addEventListener('pointerup', stopHandleDrag);
    window.addEventListener('pointercancel', stopHandleDrag);
    handlePointerMove(event);
}

function handlePointerMove(event: PointerEvent): void {
    if (activePointerId.value !== null && event.pointerId !== activePointerId.value) {
        return;
    }

    if (draggingHandleIndex.value === null || !trackRef.value) {
        return;
    }

    const handleIndex = draggingHandleIndex.value;
    const trackBounds = trackRef.value.getBoundingClientRect();
    const trackWidth = trackBounds.width;

    if (trackWidth <= 0) {
        return;
    }

    if (props.monetary) {
        updateAmount(
            clampNumber((event.clientX - trackBounds.left) / trackWidth, 0, 1) *
                props.monetary.total,
        );
        return;
    }

    const leftUser = userItems.value[handleIndex];
    const rightUser = userItems.value[handleIndex + 1];

    if (!leftUser || !rightUser) {
        return;
    }

    const prefixBasisPoints =
        handleIndex === 0 ? 0 : toBasisPoints(cumulativeBoundaries.value[handleIndex - 1] ?? 0);
    const pairTotalBasisPoints =
        toBasisPoints(leftUser.percentage) + toBasisPoints(rightUser.percentage);
    const pointerOffset = clampNumber(event.clientX - trackBounds.left, 0, trackWidth);
    const pointerBasisPoints = Math.round((pointerOffset / trackWidth) * TOTAL_BASIS_POINTS);
    const nextLeftBasisPoints = clampNumber(
        snapBasisPoints(pointerBasisPoints - prefixBasisPoints),
        0,
        pairTotalBasisPoints,
    );
    const nextRightBasisPoints = pairTotalBasisPoints - nextLeftBasisPoints;

    const nextValues = userItems.value.map((user, index) => {
        if (index === handleIndex) {
            return {
                userId: user.id,
                basisPoints: nextLeftBasisPoints,
            };
        }

        if (index === handleIndex + 1) {
            return {
                userId: user.id,
                basisPoints: nextRightBasisPoints,
            };
        }

        return {
            userId: user.id,
            basisPoints: toBasisPoints(user.percentage),
        };
    });

    emitBasisPointValues(nextValues);
}

function stopHandleDrag(): void {
    if (activeHandleElement.value && activePointerId.value !== null) {
        activeHandleElement.value.releasePointerCapture(activePointerId.value);
    }

    draggingHandleIndex.value = null;
    activePointerId.value = null;
    activeHandleElement.value = null;
    window.removeEventListener('pointermove', handlePointerMove);
    window.removeEventListener('pointerup', stopHandleDrag);
    window.removeEventListener('pointercancel', stopHandleDrag);
}

function clampNumber(value: number, min: number, max: number): number {
    return Math.min(max, Math.max(min, value));
}

onBeforeUnmount(() => {
    stopHandleDrag();
});
</script>

<template>
    <section v-if="props.monetary" class="space-y-3">
        <div class="grid grid-cols-[minmax(0,1fr)_7rem] items-center gap-3">
            <div
                ref="trackRef"
                class="relative mx-[1.375rem] h-4 touch-none rounded-(--app-radius-control) bg-(--app-color-surface-muted) select-none"
            >
                <div
                    class="absolute inset-y-0 left-0 rounded-(--app-radius-control) bg-(--app-color-primary)"
                    :style="{ width: `${monetaryPercentage}%` }"
                />
                <Button
                    type="button"
                    outlined
                    severity="secondary"
                    :disabled="props.disabled || props.monetary.total <= 0"
                    role="slider"
                    :aria-label="`Importe para ${props.monetary.recipient}`"
                    :aria-valuemin="0"
                    :aria-valuemax="props.monetary.maximum"
                    :aria-valuenow="props.monetary.amount"
                    :aria-valuetext="`${formatMoney(props.monetary.amount)} (${monetaryPercentage.toFixed(2)}%)`"
                    class="absolute top-1/2 z-10 flex h-11 w-11 -translate-y-1/2 touch-none items-center justify-center rounded-(--app-radius-control) border bg-(--app-color-surface)"
                    :style="{ left: `calc(${monetaryPercentage}% - ${HANDLE_WIDTH_PX / 2}px)` }"
                    @pointerdown.prevent="startHandleDrag(0, $event)"
                    @keydown="handleAmountKey"
                >
                    <span
                        class="h-2.5 w-0.5 rounded-(--app-radius-control) bg-(--app-color-text-subtle)"
                    />
                </Button>
            </div>
            <AppInput
                :id="amountInputId"
                v-model="amountDraft"
                :aria-label="`Monto para ${props.monetary.recipient}`"
                type="number"
                inputmode="decimal"
                min="0"
                :max="props.monetary.maximum"
                step="0.01"
                :disabled="props.disabled"
                @blur="updateAmount(Number(amountDraft))"
                @keydown.enter.prevent="updateAmount(Number(amountDraft))"
            />
        </div>
        <div class="flex flex-wrap justify-between gap-2">
            <AppText size="sm"
                >{{ props.monetary.source }}:
                {{
                    formatMoney(
                        props.monetary.sourceRemaining ??
                            props.monetary.total - props.monetary.amount,
                    )
                }}</AppText
            >
            <AppText size="sm"
                >{{ props.monetary.recipient }}: {{ formatMoney(props.monetary.amount) }} ·
                {{ monetaryPercentage.toFixed(2) }}%</AppText
            >
        </div>
    </section>
    <section v-else class="space-y-4">
        <div class="space-y-1">
            <div class="flex items-center justify-between gap-3">
                <p class="text-sm font-semibold text-(--app-color-text)">
                    Distribución por usuario
                </p>
                <AppText size="sm">Total: 100.00%</AppText>
            </div>
            <AppText size="sm">
                Arrastra los marcadores entre usuarios o ajusta el valor exacto en el campo
                numérico.
            </AppText>
        </div>

        <div class="space-y-3">
            <div
                ref="trackRef"
                class="relative mx-[1.375rem] h-4 touch-none overflow-visible rounded-(--app-radius-control) bg-(--app-color-surface-muted) select-none"
            >
                <div
                    v-for="(user, index) in userItems"
                    :key="user.id"
                    class="absolute inset-y-0"
                    :style="{
                        left: `${index === 0 ? 0 : (cumulativeBoundaries[index - 1] ?? 0)}%`,
                        width: `${user.percentage}%`,
                        backgroundColor: user.color,
                    }"
                />

                <Button
                    v-for="(user, index) in userItems.slice(0, -1)"
                    :key="`${user.id}-handle`"
                    type="button"
                    outlined
                    icon-only
                    severity="secondary"
                    class="absolute top-1/2 z-10 flex h-11 w-11 -translate-y-1/2 touch-none items-center justify-center rounded-(--app-radius-control) border bg-(--app-color-surface) transition select-none focus:ring-2 focus:ring-(--app-color-focus-ring) focus:outline-none"
                    :style="{
                        left: `calc(${cumulativeBoundaries[index] ?? 0}% - ${HANDLE_WIDTH_PX / 2}px)`,
                        borderColor: 'var(--app-color-border-strong)',
                    }"
                    :aria-label="`Ajustar límite entre ${user.name} y ${userItems[index + 1]?.name ?? ''}`"
                    @pointerdown.prevent="startHandleDrag(index, $event)"
                >
                    <span
                        class="h-2.5 w-0.5 rounded-(--app-radius-control) bg-(--app-color-text-subtle)"
                    />
                </Button>
            </div>

            <div class="grid gap-3 pt-3">
                <div
                    v-for="user in userItems"
                    :key="user.id"
                    class="border-b px-0 py-3 sm:rounded-(--app-radius-control) sm:border sm:bg-(--app-color-surface-muted) sm:px-3 sm:py-2.5"
                    :style="{ borderColor: 'var(--app-color-border)' }"
                >
                    <div class="grid grid-cols-[minmax(0,1fr)_6.5rem] items-center gap-3">
                        <div class="flex min-w-0 items-center gap-3">
                            <span
                                class="h-3 w-3 shrink-0 rounded-full"
                                :style="{ backgroundColor: user.color }"
                            />
                            <p class="text-sm font-semibold wrap-anywhere text-(--app-color-text)">
                                {{ user.name }}
                            </p>
                        </div>

                        <AppInput
                            :id="`percentage-split-${user.id}`"
                            :aria-label="`Porcentaje de ${user.name}`"
                            :model-value="inputValue(user.id, user.percentage)"
                            type="number"
                            inputmode="decimal"
                            min="0"
                            max="100"
                            step="any"
                            class="[&_input]:h-10 [&_input]:px-3"
                            @focus="handlePercentageFocus(user.id, user.percentage)"
                            @update:model-value="handlePercentageInput(user.id, $event)"
                            @blur="handlePercentageBlur(user.id)"
                        />
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>
