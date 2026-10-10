<script setup lang="ts">
import { Form, FormField, type FormSubmitEvent } from '@primevue/forms';
import { zodResolver } from '@primevue/forms/resolvers/zod';
import { z } from 'zod';
import Message from 'primevue/message';
import Select from 'primevue/select';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';

import { createAccountsRepository } from '@/modules/accounts/repositories/accountsRepository';
import type {
    Account,
    AccountLedgerDiagnostic,
    AccountLedgerRepair,
    AccountLedgerRepairPayload,
    AccountLedgerRow,
    AccountMemberAmount,
} from '@/modules/accounts/types';
import {
    AppButton,
    AppCard,
    AppInput,
    AppLoadMoreFooter,
    AppModal,
    AppText,
    AppTitle,
} from '@/modules/shared/components';
import type { AppModalAction } from '@/modules/shared/types/modal';
import { useInfiniteScroll } from '@/modules/shared/composables/useInfiniteScroll';

const props = defineProps<{ account?: Account }>();

const repository = createAccountsRepository();
const rows = ref<AccountLedgerRow[]>([]);
const isLoading = ref(false);
const isLoadingMore = ref(false);
const loadError = ref<string | null>(null);
const loadMoreError = ref<string | null>(null);
const diagnostics = ref<AccountLedgerDiagnostic[]>([]);
const repairs = ref<AccountLedgerRepair[]>([]);
const isLoadingDiagnostics = ref(false);
const diagnosticsError = ref<string | null>(null);
const actionError = ref<string | null>(null);
const actionMessage = ref<string | null>(null);
const selectedDiagnostic = ref<AccountLedgerDiagnostic | null>(null);
const selectedRepair = ref<AccountLedgerRepair | null>(null);
const isApplyingRepair = ref(false);
const custodyForm = ref<InstanceType<typeof Form> | null>(null);
const custodyResolver = zodResolver(
    z.object({
        custodyUserId: z.string().min(1, 'Selecciona un custodio.'),
        custodyAmount: z
            .string()
            .refine(
                (value) => Number.isFinite(Number(value)) && Number(value) !== 0,
                'Ingresa un monto distinto de cero.',
            ),
        custodyDescription: z.string().trim().min(1, 'Ingresa una descripción.'),
    }),
);

function submitCustody(event: FormSubmitEvent): void {
    if (event.valid) void handleDiagnosticAction('apply');
}

function requestDiagnosticAction(key: string): void {
    if (key === 'apply' && selectedDiagnostic.value?.mode === 'needs_user_input') {
        custodyForm.value?.submit();
        return;
    }
    void handleDiagnosticAction(key);
}

const custodyUserId = ref('');
const custodyAmount = ref('');
const custodyDescription = ref('');
const page = ref(1);
const hasMore = ref(false);

const accountId = computed(() => props.account?.id ?? '');
const canLoadMore = computed(
    () => !isLoading.value && !isLoadingMore.value && hasMore.value && !loadMoreError.value,
);
const openDiagnostics = computed(() => diagnostics.value.filter((item) => item.mode !== 'hidden'));
const recentReversibleRepairs = computed(() => repairs.value.filter((repair) => repair.canReverse));
const diagnosticModalActions = computed<ReadonlyArray<AppModalAction>>(() => [
    {
        key: 'cancel',
        label: 'Cancelar',
        tone: 'neutral',
        autoClose: true,
        disabled: isApplyingRepair.value,
    },
    {
        key: 'apply',
        label:
            selectedDiagnostic.value?.mode === 'needs_user_input'
                ? 'Guardar corrección'
                : 'Aplicar corrección',
        tone: 'primary',
        loading: isApplyingRepair.value,
        disabled: isApplyingRepair.value || !canApplySelectedDiagnostic.value,
    },
]);
const reverseModalActions = computed<ReadonlyArray<AppModalAction>>(() => [
    {
        key: 'cancel',
        label: 'Cancelar',
        tone: 'neutral',
        autoClose: true,
        disabled: isApplyingRepair.value,
    },
    {
        key: 'reverse',
        label: 'Reversar',
        tone: 'danger',
        loading: isApplyingRepair.value,
        disabled: isApplyingRepair.value,
    },
]);
const canApplySelectedDiagnostic = computed(() => {
    if (!selectedDiagnostic.value) {
        return false;
    }

    if (selectedDiagnostic.value.mode !== 'needs_user_input') {
        return true;
    }

    return Boolean(
        custodyUserId.value && Number(custodyAmount.value) !== 0 && custodyDescription.value.trim(),
    );
});

const { target: loadMoreSentinel } = useInfiniteScroll({
    enabled: canLoadMore,
    onIntersect: () => {
        void loadMoreLedger();
    },
});

function handleLedgerRowsUpdated(event: Event): void {
    const detail = (event as CustomEvent<{ accountId: string; rows: AccountLedgerRow[] }>).detail;

    if (!detail || detail.accountId !== accountId.value) {
        return;
    }

    rows.value = detail.rows;
    page.value = 1;
    hasMore.value = detail.rows.length >= 20;
}

onMounted(() => {
    window.addEventListener('account-ledger-rows-updated', handleLedgerRowsUpdated);
});

onBeforeUnmount(() => {
    window.removeEventListener('account-ledger-rows-updated', handleLedgerRowsUpdated);
});

function formatCurrency(value: number): string {
    return new Intl.NumberFormat('es-MX', {
        style: 'currency',
        currency: 'MXN',
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(value);
}

function formatDate(value: string | null): string {
    if (!value) {
        return 'Sin fecha';
    }

    return new Intl.DateTimeFormat('es-MX', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    }).format(new Date(`${value}T00:00:00`));
}

function visibleAmounts(amounts: AccountMemberAmount[]): AccountMemberAmount[] {
    return amounts.filter((item) => Math.abs(item.amount) > 0.004);
}

async function loadLedger(nextPage = 1): Promise<void> {
    if (!accountId.value) {
        return;
    }

    const loadingState = nextPage === 1 ? isLoading : isLoadingMore;
    loadingState.value = true;
    if (nextPage === 1) {
        loadError.value = null;
    } else {
        loadMoreError.value = null;
    }

    try {
        const result = await repository.listLedger(accountId.value, {
            page: nextPage,
            perPage: 20,
        });

        rows.value = nextPage === 1 ? result.items : [...rows.value, ...result.items];
        page.value = result.currentPage;
        hasMore.value = result.hasMore;
    } catch (error) {
        const message = error instanceof Error ? error.message : 'No se pudo cargar el libro.';

        if (nextPage === 1) {
            loadError.value = message;
        } else {
            loadMoreError.value = message;
        }
    } finally {
        loadingState.value = false;
    }
}

async function loadDiagnostics(): Promise<void> {
    if (!accountId.value) {
        return;
    }

    isLoadingDiagnostics.value = true;
    diagnosticsError.value = null;

    try {
        const result = await repository.listLedgerDiagnostics(accountId.value);
        diagnostics.value = result.diagnostics;
        repairs.value = result.repairs;
    } catch (error) {
        diagnosticsError.value =
            error instanceof Error ? error.message : 'No se pudo revisar la integridad del libro.';
    } finally {
        isLoadingDiagnostics.value = false;
    }
}

async function loadMoreLedger(): Promise<void> {
    if (!hasMore.value || isLoadingMore.value) {
        return;
    }

    await loadLedger(page.value + 1);
}

function infiniteStatusLabel(): string {
    if (isLoadingMore.value) {
        return 'Cargando más movimientos del libro...';
    }

    if (loadMoreError.value) {
        return loadMoreError.value;
    }

    if (!hasMore.value) {
        return 'Has llegado al inicio del libro.';
    }

    return 'Sigue desplazándote para revisar más movimientos.';
}

function retryLoadMore(): void {
    void loadMoreLedger();
}

function openDiagnostic(diagnostic: AccountLedgerDiagnostic): void {
    selectedDiagnostic.value = diagnostic;
    actionError.value = null;

    if (diagnostic.mode === 'needs_user_input') {
        custodyUserId.value = props.account?.users[0]?.id ?? '';
        custodyAmount.value = String(diagnostic.suggestedPayload.amount || '');
        custodyDescription.value = diagnostic.suggestedPayload.description;
    }
}

function openReverseRepair(repair: AccountLedgerRepair): void {
    selectedRepair.value = repair;
    actionError.value = null;
}

function closeDiagnosticModal(): void {
    if (isApplyingRepair.value) {
        return;
    }

    selectedDiagnostic.value = null;
}

function closeReverseModal(): void {
    if (isApplyingRepair.value) {
        return;
    }

    selectedRepair.value = null;
}

async function handleDiagnosticAction(key: string): Promise<void> {
    if (key !== 'apply' || !selectedDiagnostic.value || isApplyingRepair.value) {
        return;
    }

    await applyDiagnostic(selectedDiagnostic.value);
}

async function handleReverseAction(key: string): Promise<void> {
    if (key !== 'reverse' || !selectedRepair.value || !accountId.value) {
        return;
    }

    isApplyingRepair.value = true;
    actionError.value = null;

    try {
        await repository.reverseLedgerRepair(accountId.value, selectedRepair.value.id);
        selectedRepair.value = null;
        actionMessage.value = 'Corrección reversada. El libro fue actualizado.';
        await refreshLedgerTools();
    } catch (error) {
        actionError.value =
            error instanceof Error ? error.message : 'No se pudo reversar la corrección.';
    } finally {
        isApplyingRepair.value = false;
    }
}

async function applyDiagnostic(diagnostic: AccountLedgerDiagnostic): Promise<void> {
    if (!accountId.value) {
        return;
    }

    isApplyingRepair.value = true;
    actionError.value = null;

    try {
        await repository.repairLedger(accountId.value, buildRepairPayload(diagnostic));
        selectedDiagnostic.value = null;
        actionMessage.value = 'Corrección aplicada. Revisa el resultado en el libro.';
        await refreshLedgerTools();
    } catch (error) {
        actionError.value =
            error instanceof Error ? error.message : 'No se pudo aplicar la corrección.';
    } finally {
        isApplyingRepair.value = false;
    }
}

function buildRepairPayload(diagnostic: AccountLedgerDiagnostic): AccountLedgerRepairPayload {
    if (diagnostic.mode !== 'needs_user_input') {
        return diagnostic.suggestedPayload;
    }

    return {
        ...diagnostic.suggestedPayload,
        userId: custodyUserId.value,
        amount: Number(custodyAmount.value),
        description: custodyDescription.value.trim(),
    };
}

async function refreshLedgerTools(): Promise<void> {
    rows.value = [];
    page.value = 1;
    hasMore.value = false;
    await Promise.all([loadLedger(), loadDiagnostics()]);
}

watch(
    accountId,
    () => {
        rows.value = [];
        page.value = 1;
        hasMore.value = false;
        loadMoreError.value = null;
        actionMessage.value = null;
        void Promise.all([loadLedger(), loadDiagnostics()]);
    },
    { immediate: true },
);
</script>

<template>
    <section class="space-y-4">
        <header class="space-y-1">
            <AppTitle as="h2" size="sm">Libro</AppTitle>
            <AppText tone="subtle">
                Balance, custodia y reembolsos después de cada movimiento.
            </AppText>
        </header>

        <AppCard v-if="loadError" class="rounded-2xl border-(--app-color-danger)">
            <div class="space-y-3">
                <Message severity="error">{{ loadError }}</Message>
                <AppButton variant="outline" @click="loadLedger()">Reintentar</AppButton>
            </div>
        </AppCard>

        <div v-else-if="isLoading" class="space-y-3">
            <div
                v-for="index in 4"
                :key="index"
                class="h-32 animate-pulse rounded-2xl bg-(--app-color-surface-muted)"
            />
        </div>

        <AppCard v-else-if="rows.length === 0" class="rounded-2xl">
            <AppText>No hay movimientos en el libro de esta cuenta.</AppText>
        </AppCard>

        <div v-else class="overflow-hidden rounded-2xl border border-(--app-color-border)">
            <article
                v-for="row in rows"
                :key="row.id"
                class="space-y-3 border-b border-(--app-color-border) bg-(--app-color-surface) px-4 py-4 last:border-b-0"
            >
                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div class="min-w-0">
                        <h3 class="text-base font-semibold break-words text-(--app-color-text)">
                            {{ row.label }}
                        </h3>
                        <p class="mt-1 text-xs font-medium text-(--app-color-text-subtle)">
                            {{ formatDate(row.occurredAt) }} · {{ row.description }}
                        </p>
                    </div>
                    <div class="min-w-0 sm:shrink-0 sm:text-right">
                        <p
                            class="text-sm font-bold"
                            :class="
                                row.sourceType === 'transaction' && row.amount > 0
                                    ? 'text-(--app-color-text)'
                                    : 'text-(--app-color-text)'
                            "
                        >
                            {{ formatCurrency(row.amount) }}
                        </p>
                        <p class="text-xs text-(--app-color-text-subtle)">
                            Balance {{ formatCurrency(row.balanceAfter) }}
                        </p>
                    </div>
                </div>

                <div v-if="row.allocations.length" class="flex flex-wrap gap-2">
                    <span
                        v-for="allocation in row.allocations"
                        :key="`${row.id}-${allocation.userId}`"
                        class="rounded-full bg-(--app-color-surface-muted) px-3 py-1 text-xs font-semibold text-(--app-color-text-subtle)"
                    >
                        {{ allocation.userName ?? 'Usuario' }}
                        {{ formatCurrency(allocation.amount) }}
                    </span>
                </div>

                <div class="grid gap-3 sm:grid-cols-2">
                    <div v-if="visibleAmounts(row.custodyAfterByUser).length" class="space-y-1">
                        <p class="text-xs font-semibold text-(--app-color-text-subtle) uppercase">
                            Custodia
                        </p>
                        <p
                            v-for="custody in visibleAmounts(row.custodyAfterByUser)"
                            :key="`${row.id}-custody-${custody.userId}`"
                            class="flex justify-between gap-3 text-xs text-(--app-color-text-subtle)"
                        >
                            <span class="break-words">{{ custody.userName }}</span>
                            <span class="shrink-0 font-semibold">{{
                                formatCurrency(custody.amount)
                            }}</span>
                        </p>
                    </div>

                    <div v-if="visibleAmounts(row.settlementAfterByUser).length" class="space-y-1">
                        <p class="text-xs font-semibold text-(--app-color-text-subtle) uppercase">
                            Reembolsos
                        </p>
                        <p
                            v-for="settlement in visibleAmounts(row.settlementAfterByUser)"
                            :key="`${row.id}-settlement-${settlement.userId}`"
                            class="flex justify-between gap-3 text-xs text-(--app-color-text-subtle)"
                        >
                            <span class="break-words">{{ settlement.userName }}</span>
                            <span class="shrink-0 font-semibold">{{
                                formatCurrency(settlement.amount)
                            }}</span>
                        </p>
                    </div>
                </div>
            </article>
        </div>

        <section class="space-y-3">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div class="min-w-0">
                    <AppTitle as="h3" size="sm" class="text-base!">Integridad</AppTitle>
                    <AppText tone="subtle">
                        Correcciones auditables para custodia y reembolsos del libro.
                    </AppText>
                </div>
                <AppButton
                    variant="outline"
                    :disabled="isLoadingDiagnostics"
                    @click="loadDiagnostics()"
                >
                    Revisar
                </AppButton>
            </div>

            <Message v-if="diagnosticsError" severity="error">{{ diagnosticsError }}</Message>

            <Message v-else-if="actionMessage" severity="success">{{ actionMessage }}</Message>

            <div
                v-if="isLoadingDiagnostics"
                class="h-20 animate-pulse rounded-2xl bg-(--app-color-surface-muted)"
            />

            <div v-else class="overflow-hidden rounded-2xl border border-(--app-color-border)">
                <article
                    v-if="openDiagnostics.length === 0"
                    class="bg-(--app-color-surface) px-4 py-4"
                >
                    <p class="text-sm font-semibold text-(--app-color-text)">
                        Sin inconsistencias abiertas
                    </p>
                    <p class="mt-1 text-xs text-(--app-color-text-subtle)">
                        El balance, la custodia y los reembolsos no muestran huecos accionables.
                    </p>
                </article>

                <article
                    v-for="diagnostic in openDiagnostics"
                    v-else
                    :key="diagnostic.id"
                    class="border-b border-(--app-color-border) bg-(--app-color-surface) px-4 py-4 last:border-b-0"
                >
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div class="min-w-0">
                            <p class="text-sm font-bold text-(--app-color-text)">
                                {{ diagnostic.title }}
                            </p>
                            <p class="mt-1 text-xs leading-5 text-(--app-color-text-subtle)">
                                {{ diagnostic.description }}
                            </p>
                        </div>
                        <AppButton variant="secondary" @click="openDiagnostic(diagnostic)">
                            {{ diagnostic.mode === 'needs_user_input' ? 'Completar' : 'Corregir' }}
                        </AppButton>
                    </div>
                </article>
            </div>

            <div
                v-if="recentReversibleRepairs.length"
                class="overflow-hidden rounded-2xl border border-(--app-color-border)"
            >
                <article
                    v-for="repair in recentReversibleRepairs"
                    :key="repair.id"
                    class="border-b border-(--app-color-border) bg-(--app-color-surface) px-4 py-4 last:border-b-0"
                >
                    <div class="flex items-center justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-sm font-bold break-words text-(--app-color-text)">
                                {{ repair.description }}
                            </p>
                            <p class="mt-1 text-xs text-(--app-color-text-subtle)">
                                {{ formatCurrency(repair.amount) }} · {{ repair.actorUserName }}
                            </p>
                        </div>
                        <AppButton variant="outline" @click="openReverseRepair(repair)"
                            >Reversar</AppButton
                        >
                    </div>
                </article>
            </div>
        </section>

        <div v-if="rows.length" ref="loadMoreSentinel">
            <AppLoadMoreFooter
                :label="infiniteStatusLabel()"
                :show-retry="Boolean(loadMoreError)"
                @retry="retryLoadMore"
            />
        </div>

        <AppModal
            :open="Boolean(selectedDiagnostic)"
            title="Confirmar corrección"
            variant="warning"
            :actions="diagnosticModalActions"
            @close="closeDiagnosticModal"
            @action="requestDiagnosticAction"
        >
            <div v-if="selectedDiagnostic" class="space-y-4">
                <div>
                    <p class="text-base font-bold text-(--app-color-text)">
                        {{ selectedDiagnostic.title }}
                    </p>
                    <p class="mt-1 text-sm leading-6 text-(--app-color-text-subtle)">
                        {{ selectedDiagnostic.description }}
                    </p>
                </div>

                <Form
                    v-if="selectedDiagnostic.mode === 'needs_user_input'"
                    :key="selectedDiagnostic.id"
                    ref="custodyForm"
                    :resolver="custodyResolver"
                    @submit="submitCustody"
                    class="space-y-3 rounded-2xl border border-(--app-color-border) bg-(--app-color-surface-muted) p-3"
                >
                    <label
                        class="block text-xs font-semibold text-(--app-color-text-subtle)"
                        for="custody-user"
                    >
                        Custodio
                    </label>
                    <FormField name="custodyUserId" :initial-value="custodyUserId" v-slot="$field">
                        <Select
                            input-id="custody-user"
                            v-model="custodyUserId"
                            @update:model-value="$field.props.onChange({ value: $event })"
                            :options="props.account?.users ?? []"
                            option-label="name"
                            option-value="id"
                            class="w-full"
                        />
                        <Message
                            v-if="$field.invalid"
                            severity="error"
                            size="small"
                            variant="simple"
                            >{{ $field.error?.message }}</Message
                        >
                    </FormField>

                    <FormField name="custodyAmount" :initial-value="custodyAmount" v-slot="$field">
                        <AppInput
                            id="custody-amount"
                            v-model="custodyAmount"
                            label="Monto de custodia"
                            mask="amount"
                            @update:model-value="$field.props.onChange({ value: $event })"
                            :error="$field.error?.message"
                        />
                    </FormField>
                    <FormField
                        name="custodyDescription"
                        :initial-value="custodyDescription"
                        v-slot="$field"
                    >
                        <AppInput
                            id="custody-description"
                            v-model="custodyDescription"
                            label="Descripción"
                            @update:model-value="$field.props.onChange({ value: $event })"
                            :error="$field.error?.message"
                        />
                    </FormField>
                </Form>

                <div
                    class="rounded-2xl border border-(--app-color-border) bg-(--app-color-surface-muted) p-3"
                >
                    <p class="text-sm font-semibold text-(--app-color-text)">
                        {{ selectedDiagnostic.preview.summary }}
                    </p>
                    <div
                        v-if="selectedDiagnostic.preview.ledgerEntries.length"
                        class="mt-3 divide-y divide-(--app-color-border)"
                    >
                        <p
                            v-for="entry in selectedDiagnostic.preview.ledgerEntries"
                            :key="`${entry.userId}-${entry.relatedUserId}-${entry.amount}`"
                            class="flex justify-between gap-3 py-2 text-xs text-(--app-color-text-subtle)"
                        >
                            <span class="min-w-0 break-words">
                                {{ entry.userName ?? 'Usuario' }}
                                <template v-if="entry.relatedUserName">
                                    / {{ entry.relatedUserName }}</template
                                >
                            </span>
                            <span class="shrink-0 font-bold text-(--app-color-text)">
                                {{ formatCurrency(entry.amount) }}
                            </span>
                        </p>
                    </div>
                </div>

                <Message v-if="actionError" severity="error">{{ actionError }}</Message>
            </div>
        </AppModal>

        <AppModal
            :open="Boolean(selectedRepair)"
            title="Reversar corrección"
            variant="danger"
            :actions="reverseModalActions"
            @close="closeReverseModal"
            @action="handleReverseAction"
        >
            <div v-if="selectedRepair" class="space-y-4">
                <p class="text-sm leading-6 text-(--app-color-text-subtle)">
                    Se crearán asientos inversos para la corrección aplicada por
                    {{ selectedRepair.actorUserName }}. El movimiento original no se modifica.
                </p>
                <div
                    class="rounded-2xl border border-(--app-color-border) bg-(--app-color-surface-muted) p-3"
                >
                    <p class="text-sm font-bold text-(--app-color-text)">
                        {{ selectedRepair.description }}
                    </p>
                    <p class="mt-1 text-xs text-(--app-color-text-subtle)">
                        {{ formatCurrency(selectedRepair.amount) }}
                    </p>
                </div>
                <Message v-if="actionError" severity="error">{{ actionError }}</Message>
            </div>
        </AppModal>
    </section>
</template>
