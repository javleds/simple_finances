<script setup lang="ts">
import { Form, FormField, type FormSubmitEvent } from '@primevue/forms';
import { zodResolver } from '@primevue/forms/resolvers/zod';
import { z } from 'zod';
import Message from 'primevue/message';
import Button from 'primevue/button';
import { BanknotesIcon, PlusIcon } from '@heroicons/vue/24/outline';
import { computed, onMounted, ref, watch } from 'vue';

import { useVirtualAccounts } from '@/modules/virtual-accounts/composables/useVirtualAccounts';
import type { VirtualAccountItem } from '@/modules/virtual-accounts/types';
import {
    AppButton,
    AppCard,
    AppDatePicker,
    AppHeroMetric,
    AppInput,
    AppListState,
    AppModal,
    AppText,
    AppTitle,
} from '@/modules/shared/components';
import type { AppModalAction } from '@/modules/shared/types/modal';

const {
    dashboard,
    selectedAccount,
    selectedAccountId,
    selectedSnapshots,
    isLoading,
    isLoadingSnapshots,
    isSaving,
    loadError,
    saveError,
    loadDashboard,
    loadSnapshots,
    captureSnapshot,
} = useVirtualAccounts();

const captureForm = ref<InstanceType<typeof Form> | null>(null);
const captureResolver = zodResolver(
    z.object({
        observedBalance: z
            .string()
            .refine((value) => parseAmount(value) !== null, 'Ingresa un saldo válido.'),
        observedAt: z.string().min(1, 'Selecciona la fecha del corte.'),
        notes: z.string(),
    }),
);

function submitCapture(event: FormSubmitEvent): void {
    if (event.valid) void handleCaptureAction('save');
}

function requestCapture(action: string): void {
    if (action === 'save') captureForm.value?.submit();
}

const isCaptureOpen = ref(false);
const observedBalance = ref('');
const observedAt = ref(today());
const notes = ref('');

const hasAccounts = computed(() => dashboard.value.accounts.length > 0);
const canSubmitCapture = computed(() => parseAmount(observedBalance.value) !== null);
const captureActions = computed<ReadonlyArray<AppModalAction>>(() => [
    {
        key: 'cancel',
        label: 'Cancelar',
        tone: 'neutral',
        autoClose: true,
    },
    {
        key: 'save',
        label: 'Guardar',
        tone: 'primary',
        disabled: !canSubmitCapture.value,
        loading: isSaving.value,
    },
]);

onMounted(() => {
    void loadDashboard();
});

watch(
    () => selectedAccount.value?.accountId,
    (accountId) => {
        if (!accountId) {
            return;
        }

        void loadSnapshots(accountId);
    },
);

function openCapture(account: VirtualAccountItem): void {
    selectedAccountId.value = account.accountId;
    observedBalance.value = account.currentBalance.toFixed(2);
    observedAt.value = today();
    notes.value = '';
    isCaptureOpen.value = true;
}

function closeCapture(): void {
    isCaptureOpen.value = false;
}

async function handleCaptureAction(actionKey: string): Promise<void> {
    if (actionKey !== 'save' || isSaving.value) {
        return;
    }

    const amount = parseAmount(observedBalance.value);

    if (amount === null) {
        return;
    }

    const wasCaptured = await captureSnapshot({
        observedBalance: amount,
        observedAt: observedAt.value,
        notes: notes.value.trim() || null,
    });

    if (wasCaptured) {
        closeCapture();
    }
}

function selectAccount(account: VirtualAccountItem): void {
    selectedAccountId.value = account.accountId;
}

function formatCurrency(value: number): string {
    return new Intl.NumberFormat('es-MX', {
        style: 'currency',
        currency: 'MXN',
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(value);
}

function formatDate(value: string | null | undefined): string {
    if (!value) {
        return 'Sin corte';
    }

    return new Intl.DateTimeFormat('es-MX', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    }).format(new Date(value));
}

function parseAmount(value: string): number | null {
    const amount = Number(value);

    if (!Number.isFinite(amount) || amount < 0) {
        return null;
    }

    return amount;
}

function today(): string {
    return new Date().toISOString().slice(0, 10);
}
</script>

<template>
    <section class="space-y-4 sm:space-y-5">
        <header class="flex items-start justify-between gap-4 px-1">
            <div class="min-w-0 space-y-1">
                <AppTitle as="h1">Cuentas virtuales</AppTitle>
                <AppText tone="subtle">
                    Ahorro, inversiones y apartados con saldo observado.
                </AppText>
            </div>
        </header>

        <AppListState
            :error="loadError"
            :has-items="hasAccounts"
            :is-loading="isLoading"
            loading-label="Cargando cuentas virtuales..."
            @retry="loadDashboard"
        >
            <div class="space-y-4 sm:space-y-5">
                <AppCard class="rounded-(--app-radius-control) sm:rounded-(--app-radius-control)">
                    <div class="space-y-4 sm:space-y-5">
                        <AppHeroMetric
                            label="Total actual"
                            :value="formatCurrency(dashboard.summary.currentBalance)"
                        >
                            <template #adornment>
                                <BanknotesIcon class="h-9 w-9 text-(--app-color-primary)" />
                            </template>
                        </AppHeroMetric>

                        <div class="grid grid-cols-2 gap-3">
                            <div
                                class="min-w-0 py-2 sm:rounded-(--app-radius-control) sm:border sm:px-3 sm:py-3"
                                :style="{ borderColor: 'var(--app-color-border)' }"
                            >
                                <AppText size="sm" tone="subtle">Capital neto</AppText>
                                <p
                                    class="text-base font-semibold text-(--app-color-text) tabular-nums"
                                >
                                    {{ formatCurrency(dashboard.summary.netCapital) }}
                                </p>
                            </div>
                            <div
                                class="min-w-0 py-2 sm:rounded-(--app-radius-control) sm:border sm:px-3 sm:py-3"
                                :style="{ borderColor: 'var(--app-color-border)' }"
                            >
                                <AppText size="sm" tone="subtle">Rendimiento</AppText>
                                <p
                                    class="text-base font-semibold text-emerald-700 tabular-nums dark:text-emerald-300"
                                >
                                    {{ formatCurrency(dashboard.summary.observedYield) }}
                                </p>
                            </div>
                            <div
                                class="min-w-0 py-2 sm:rounded-(--app-radius-control) sm:border sm:px-3 sm:py-3"
                                :style="{ borderColor: 'var(--app-color-border)' }"
                            >
                                <AppText size="sm" tone="subtle">Aportado</AppText>
                                <p
                                    class="text-base font-semibold text-(--app-color-text) tabular-nums"
                                >
                                    {{ formatCurrency(dashboard.summary.manualContributions) }}
                                </p>
                            </div>
                            <div
                                class="min-w-0 py-2 sm:rounded-(--app-radius-control) sm:border sm:px-3 sm:py-3"
                                :style="{ borderColor: 'var(--app-color-border)' }"
                            >
                                <AppText size="sm" tone="subtle">Retirado</AppText>
                                <p
                                    class="text-base font-semibold text-(--app-color-text) tabular-nums"
                                >
                                    {{ formatCurrency(dashboard.summary.manualWithdrawals) }}
                                </p>
                            </div>
                        </div>
                    </div>
                </AppCard>

                <section class="space-y-3">
                    <AppTitle as="h2" size="sm">Apartados</AppTitle>

                    <article
                        v-for="account in dashboard.accounts"
                        :key="account.accountId"
                        class="rounded-(--app-radius-control) border bg-(--app-color-surface) px-4 py-4 transition"
                        :class="
                            selectedAccount?.accountId === account.accountId
                                ? 'border-(--app-color-primary)'
                                : 'border-(--app-color-border)'
                        "
                    >
                        <Button
                            variant="text"
                            severity="secondary"
                            type="button"
                            class="w-full justify-start! p-0! text-left"
                            @click="selectAccount(account)"
                        >
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p
                                        class="text-sm font-semibold break-words text-(--app-color-text)"
                                    >
                                        {{ account.accountName }}
                                    </p>
                                    <AppText size="sm" tone="subtle">
                                        Ultimo corte:
                                        {{ formatDate(account.latestSnapshot?.observedAt) }}
                                    </AppText>
                                </div>
                                <p
                                    class="shrink-0 text-sm font-semibold text-(--app-color-text) tabular-nums"
                                >
                                    {{ formatCurrency(account.currentBalance) }}
                                </p>
                            </div>
                        </Button>

                        <div class="mt-4 grid grid-cols-2 gap-3 text-sm sm:grid-cols-3">
                            <div>
                                <AppText size="sm" tone="subtle">Inicial</AppText>
                                <p class="font-semibold tabular-nums">
                                    {{ formatCurrency(account.initialBalance) }}
                                </p>
                            </div>
                            <div>
                                <AppText size="sm" tone="subtle">Retirado</AppText>
                                <p class="font-semibold tabular-nums">
                                    {{ formatCurrency(account.manualWithdrawals) }}
                                </p>
                            </div>
                            <div>
                                <AppText size="sm" tone="subtle">Interes</AppText>
                                <p
                                    class="font-semibold text-emerald-700 tabular-nums dark:text-emerald-300"
                                >
                                    {{ formatCurrency(account.observedYield) }}
                                </p>
                            </div>
                        </div>

                        <div class="mt-4">
                            <AppButton variant="secondary" full-width @click="openCapture(account)">
                                <PlusIcon class="h-5 w-5" />
                                Capturar saldo
                            </AppButton>
                        </div>
                    </article>
                </section>

                <AppCard v-if="selectedAccount" class="rounded-(--app-radius-control) sm:rounded-(--app-radius-control)">
                    <div class="space-y-4">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="min-w-0">
                                <AppTitle as="h2" size="sm">Historial observado</AppTitle>
                                <AppText tone="subtle">{{ selectedAccount.accountName }}</AppText>
                            </div>
                        </div>

                        <div v-if="isLoadingSnapshots" class="py-6 text-center">
                            <AppText>Cargando historial...</AppText>
                        </div>

                        <div
                            v-else-if="selectedSnapshots.length === 0"
                            class="rounded-(--app-radius-control) border px-4 py-5 text-center"
                            :style="{ borderColor: 'var(--app-color-border)' }"
                        >
                            <AppText>Sin cortes capturados.</AppText>
                        </div>

                        <div v-else class="space-y-3">
                            <div
                                v-for="snapshot in selectedSnapshots"
                                :key="snapshot.id"
                                class="border-b px-0 py-3 sm:rounded-(--app-radius-control) sm:border sm:px-4"
                                :style="{ borderColor: 'var(--app-color-border)' }"
                            >
                                <div class="flex flex-wrap items-start justify-between gap-3">
                                    <div>
                                        <p class="text-sm font-semibold text-(--app-color-text)">
                                            {{ formatDate(snapshot.observedAt) }}
                                        </p>
                                        <AppText v-if="snapshot.notes" size="sm" tone="subtle">
                                            {{ snapshot.notes }}
                                        </AppText>
                                    </div>
                                    <p
                                        class="text-sm font-semibold tabular-nums"
                                        :class="
                                            snapshot.delta >= 0
                                                ? 'text-emerald-700 dark:text-emerald-300'
                                                : 'text-red-700 dark:text-red-300'
                                        "
                                    >
                                        {{ formatCurrency(snapshot.delta) }}
                                    </p>
                                </div>
                                <div
                                    class="mt-3 flex justify-between gap-3 text-xs text-(--app-color-text-subtle)"
                                >
                                    <span
                                        >Anterior
                                        {{ formatCurrency(snapshot.previousBalance) }}</span
                                    >
                                    <span
                                        >Final {{ formatCurrency(snapshot.observedBalance) }}</span
                                    >
                                </div>
                            </div>
                        </div>
                    </div>
                </AppCard>
            </div>
        </AppListState>

        <AppModal
            presentation="sheet"
            :open="isCaptureOpen"
            title="Capturar saldo observado"
            :actions="captureActions"
            @close="closeCapture"
            @action="requestCapture"
        >
            <Form
                :key="`${isCaptureOpen}-${selectedAccountId}`"
                ref="captureForm"
                :resolver="captureResolver"
                class="space-y-4 sm:space-y-5"
                @submit="submitCapture"
            >
                <Message v-if="saveError" severity="error">{{ saveError }}</Message>

                <FormField v-slot="$field" name="observedBalance" :initial-value="observedBalance">
                    <AppInput
                        id="virtual-observed-balance"
                        v-model="observedBalance"
                        label="Saldo observado"
                        mask="amount"
                        type="number"
                        inputmode="decimal"
                        min="0"
                        step="0.01"
                        required
                        @update:model-value="$field.props.onChange({ value: $event })"
                        :error="$field.error?.message"
                    />
                </FormField>

                <FormField v-slot="$field" name="observedAt" :initial-value="observedAt">
                    <AppDatePicker
                        id="virtual-observed-at"
                        v-model="observedAt"
                        label="Fecha del corte"
                        required
                        @update:model-value="$field.props.onChange({ value: $event })"
                        :error="$field.error?.message"
                    />
                </FormField>

                <FormField v-slot="$field" name="notes" :initial-value="notes">
                    <AppInput
                        id="virtual-snapshot-notes"
                        v-model="notes"
                        label="Notas"
                        placeholder="Ej. Estado de cuenta mensual"
                        @update:model-value="$field.props.onChange({ value: $event })"
                        :error="$field.error?.message"
                    />
                </FormField>
            </Form>
        </AppModal>
    </section>
</template>
