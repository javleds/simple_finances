<script setup lang="ts">
import Button from 'primevue/button';
import { computed, ref } from 'vue';
import { ArrowLeftIcon, ArrowPathIcon, PlusIcon, TrashIcon } from '@heroicons/vue/24/outline';
import { RouterLink } from 'vue-router';

import { AppButton, AppCard, AppInput, AppText, AppTitle } from '@/modules/shared/components';

type CreditCardDraft = {
    id: number;
    description: string;
    total: string;
    covered: string;
};

const cards = ref<CreditCardDraft[]>([createDraft(1)]);
let nextCardId = 2;

const currencyFormatter = new Intl.NumberFormat('es-MX', {
    style: 'currency',
    currency: 'MXN',
});

const totalDebt = computed(() =>
    cards.value.reduce((sum, card) => sum + parseAmount(card.total), 0),
);
const totalCovered = computed(() =>
    cards.value.reduce((sum, card) => sum + parseAmount(card.covered), 0),
);
const totalDifference = computed(() => totalDebt.value - totalCovered.value);
const hasTotals = computed(() => cards.value.some((card) => parseAmount(card.total) > 0));
const totalStatusLabel = computed(() => {
    if (!hasTotals.value) {
        return 'Agrega una deuda para ver el total pendiente';
    }

    if (totalDifference.value > 0) {
        return 'por cubrir entre todas tus tarjetas';
    }

    if (totalDifference.value === 0) {
        return 'deuda cubierta entre todas tus tarjetas';
    }

    return 'de excedente entre todas tus tarjetas';
});

function createDraft(id: number): CreditCardDraft {
    return { id, description: '', total: '', covered: '' };
}

function parseAmount(value: string): number {
    const amount = Number.parseFloat(value.replace(',', '.'));

    return Number.isFinite(amount) && amount >= 0 ? amount : 0;
}

function formatCurrency(value: number): string {
    return currencyFormatter.format(value);
}

function differenceFor(card: CreditCardDraft): number {
    return parseAmount(card.total) - parseAmount(card.covered);
}

function statusFor(card: CreditCardDraft): 'empty' | 'pending' | 'covered' | 'surplus' {
    if (parseAmount(card.total) === 0) {
        return 'empty';
    }

    const difference = differenceFor(card);

    if (difference > 0) {
        return 'pending';
    }

    return difference === 0 ? 'covered' : 'surplus';
}

function statusLabel(card: CreditCardDraft): string {
    const status = statusFor(card);

    if (status === 'empty') return 'Ingresa la deuda total';
    if (status === 'pending') return `Faltan ${formatCurrency(differenceFor(card))}`;
    if (status === 'covered') return 'Deuda cubierta';

    return `Excedente ${formatCurrency(Math.abs(differenceFor(card)))}`;
}

function addCard(): void {
    cards.value.push(createDraft(nextCardId));
    nextCardId += 1;
}

function removeCard(id: number): void {
    if (cards.value.length === 1) {
        const onlyCard = cards.value[0];

        if (onlyCard) {
            cards.value[0] = createDraft(onlyCard.id);
        }

        return;
    }

    cards.value = cards.value.filter((card) => card.id !== id);
}

function resetCards(): void {
    cards.value = [createDraft(nextCardId)];
    nextCardId += 1;
}
</script>

<template>
    <div class="space-y-6">
        <div class="flex items-center gap-3">
            <RouterLink
                :to="{ name: 'admin.settings' }"
                class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full border bg-(--app-color-surface-muted) text-(--app-color-text) transition hover:bg-(--app-color-surface) focus:ring-4 focus:ring-(--app-color-focus-ring) focus:outline-none"
                :style="{ borderColor: 'var(--app-color-border)' }"
                aria-label="Volver a configuración"
            >
                <ArrowLeftIcon class="h-5 w-5" />
            </RouterLink>
            <div class="min-w-0 space-y-1">
                <AppTitle as="h1">Pago de tarjetas</AppTitle>
                <AppText>Compara tu deuda con el dinero que ya tienes apartado.</AppText>
            </div>
        </div>

        <AppCard muted class="rounded-2xl! p-4! sm:rounded-3xl! sm:p-5!">
            <div class="space-y-2">
                <AppText size="sm" tone="muted">Solo es una ayuda temporal</AppText>
                <AppText size="sm"
                    >Los datos viven únicamente mientras mantengas esta pantalla abierta. Se borran
                    al refrescar o salir.</AppText
                >
            </div>
        </AppCard>

        <section class="space-y-4" aria-labelledby="cards-title">
            <div class="flex items-end justify-between gap-3">
                <div class="space-y-1">
                    <AppTitle id="cards-title" as="h2" size="sm">Tus tarjetas</AppTitle>
                    <AppText size="sm"
                        >Agrega una fila por cada tarjeta que quieras revisar.</AppText
                    >
                </div>
                <Button
                    variant="text"
                    severity="secondary"
                    type="button"
                    class="inline-flex shrink-0 items-center gap-1.5 rounded-xl px-2 py-2 text-sm font-semibold text-(--app-color-link) transition hover:bg-(--app-color-surface-muted) focus:ring-4 focus:ring-(--app-color-focus-ring) focus:outline-none"
                    @click="addCard"
                >
                    <PlusIcon class="h-5 w-5" />
                    <span>Agregar</span>
                </Button>
            </div>

            <div class="space-y-4">
                <AppCard
                    v-for="(card, index) in cards"
                    :key="card.id"
                    class="rounded-2xl! p-4! sm:rounded-3xl! sm:p-5!"
                >
                    <div class="space-y-4">
                        <div class="flex items-center justify-between gap-3">
                            <p class="text-sm font-semibold text-(--app-color-text)">
                                Tarjeta {{ index + 1 }}
                            </p>
                            <Button
                                variant="text"
                                severity="secondary"
                                type="button"
                                class="inline-flex h-11 w-11 items-center justify-center rounded-full text-(--app-color-text-subtle) transition hover:bg-(--app-color-surface-muted) hover:text-(--app-color-danger) focus:ring-4 focus:ring-(--app-color-focus-ring) focus:outline-none"
                                :aria-label="`Limpiar tarjeta ${index + 1}`"
                                @click="removeCard(card.id)"
                            >
                                <TrashIcon class="h-5 w-5" />
                            </Button>
                        </div>

                        <AppInput
                            :id="`card-description-${card.id}`"
                            v-model="card.description"
                            label="Tarjeta o nota (opcional)"
                            placeholder="Ej. Tarjeta A"
                            autocomplete="off"
                        />

                        <div class="grid gap-4 sm:grid-cols-2">
                            <AppInput
                                :id="`card-total-${card.id}`"
                                v-model="card.total"
                                label="Deuda total"
                                mask="amount"
                                inputmode="decimal"
                                placeholder="$ 0.00"
                                autocomplete="off"
                            />
                            <AppInput
                                :id="`card-covered-${card.id}`"
                                v-model="card.covered"
                                label="Ahorrado"
                                mask="amount"
                                inputmode="decimal"
                                placeholder="$ 0.00"
                                autocomplete="off"
                            />
                        </div>

                        <div
                            class="border-t pt-4 sm:rounded-2xl sm:border sm:px-4 sm:pb-4"
                            :class="{
                                'border-(--app-color-border) bg-(--app-color-surface-muted)':
                                    statusFor(card) === 'empty',
                                'border-(--app-color-danger) bg-(--app-color-danger)/5':
                                    statusFor(card) === 'pending',
                                'border-(--app-color-success) bg-(--app-color-success)/5':
                                    statusFor(card) === 'covered',
                                'border-(--app-color-primary) bg-(--app-color-primary)/5':
                                    statusFor(card) === 'surplus',
                            }"
                            aria-live="polite"
                        >
                            <AppText size="sm" tone="subtle">Resultado</AppText>
                            <p
                                class="mt-1 text-xl font-semibold"
                                :class="{
                                    'text-(--app-color-text)': statusFor(card) === 'empty',
                                    'text-(--app-color-danger)': statusFor(card) === 'pending',
                                    'text-(--app-color-success)': statusFor(card) === 'covered',
                                    'text-(--app-color-primary)': statusFor(card) === 'surplus',
                                }"
                            >
                                {{ statusLabel(card) }}
                            </p>
                            <AppText v-if="statusFor(card) !== 'empty'" size="sm" class="mt-1">
                                Diferencia: {{ formatCurrency(differenceFor(card)) }}
                            </AppText>
                        </div>
                    </div>
                </AppCard>
            </div>
        </section>

        <AppCard
            class="rounded-2xl! p-4! sm:bg-(--app-color-primary)! sm:p-5! sm:text-(--app-color-primary-foreground)!"
        >
            <div class="space-y-4">
                <div>
                    <p class="text-sm opacity-80">Panorama general</p>
                    <p class="mt-1 text-3xl font-semibold tracking-tight">
                        {{ hasTotals ? formatCurrency(Math.abs(totalDifference)) : '$ 0.00' }}
                    </p>
                    <p class="mt-1 text-sm opacity-80">{{ totalStatusLabel }}</p>
                </div>
                <div
                    class="grid grid-cols-2 gap-3 border-t border-(--app-color-border) pt-4 text-sm sm:border-white/20"
                >
                    <div>
                        <p class="opacity-75">Deuda total</p>
                        <p class="mt-1 font-semibold">{{ formatCurrency(totalDebt) }}</p>
                    </div>
                    <div>
                        <p class="opacity-75">Ahorrado</p>
                        <p class="mt-1 font-semibold">{{ formatCurrency(totalCovered) }}</p>
                    </div>
                </div>
            </div>
        </AppCard>

        <AppButton variant="ghost" class="w-full" @click="resetCards">
            <ArrowPathIcon class="h-5 w-5" />
            Reiniciar cálculo
        </AppButton>
    </div>
</template>
