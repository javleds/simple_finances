<script setup lang="ts">
import Message from 'primevue/message';
import TransactionCategorySelect from './TransactionCategorySelect.vue';
import Checkbox from 'primevue/checkbox';
import { computed, ref, watch, watchEffect } from 'vue';

import type { AccountMember } from '@/modules/accounts/types';
import { useTransactionForm } from '@/modules/transactions/composables/useTransactionForm';
import type {
    Transaction,
    TransactionSubmitOptions,
    TransactionWritePayload,
} from '@/modules/transactions/types';
import {
    AppDatePicker,
    AppInput,
    AppPercentageSplitEditor,
    AppSearchSelect,
    AppSwitch,
    AppText,
    AppToggleButton,
} from '@/modules/shared/components';
import { useFormFieldInteraction } from '@/modules/shared/composables/useFormFieldInteraction';

type FormState = {
    canSubmit: boolean;
    isSubmitting: boolean;
    keepOpen: boolean;
};

const props = withDefaults(
    defineProps<{
        formId?: string;
        accountUsers?: ReadonlyArray<AccountMember>;
        accountBalance?: number | null;
        financialGoals?: ReadonlyArray<{
            id: string;
            name: string;
            description?: string | null;
        }>;
        enableCreateAndAddAnother?: boolean;
        isLoadingFinancialGoals?: boolean;
        initialValues?: Partial<Transaction> | null;
        currentUserId?: string | null;
        lockedAccountId?: string | null;
        serverError?: string | null;
    }>(),
    {
        formId: 'transaction-form',
        accountUsers: () => [],
        accountBalance: null,
        financialGoals: () => [],
        enableCreateAndAddAnother: false,
        isLoadingFinancialGoals: false,
        initialValues: null,
        currentUserId: null,
        lockedAccountId: null,
        serverError: null,
    },
);

const emit = defineEmits<{
    submit: [payload: TransactionWritePayload];
    submitWithOptions: [payload: TransactionWritePayload, options: TransactionSubmitOptions];
    stateChange: [payload: FormState];
}>();

const transactionTypeOptions = [
    { value: 'income', label: 'Ingreso' },
    { value: 'expense', label: 'Egreso' },
] as const;

const {
    type,
    categoryId,
    accountId,
    concept,
    amount,
    paidByUserId,
    custodianUserId,
    paymentSource,
    splitBetweenUsers,
    date,
    financialGoalId,
    userPayments,
    isSubmitting,
    isSubmitDisabled,
    meta,
    isIncome,
    isExpense,
    submitForm,
} = useTransactionForm({
    initialValues: () => props.initialValues,
    lockedAccountId: props.lockedAccountId,
});

const hasSharedAccount = computed(() => props.accountUsers.length > 1);
const showUserSplitToggle = computed(() => isExpense.value && hasSharedAccount.value);
const showUserSplitInputs = computed(() => showUserSplitToggle.value && splitBetweenUsers.value);
const accountUserOptions = computed(() =>
    props.accountUsers.map((user) => ({
        value: user.id,
        label: user.name,
        description: user.email,
    })),
);
const hasNoAccountFunds = computed(
    () => hasSharedAccount.value && props.accountBalance !== null && props.accountBalance <= 0,
);
const requiresPersonalPayment = computed(() => hasNoAccountFunds.value && !props.initialValues?.id);
const paymentSourceOptions = computed(() => [
    {
        value: 'account_fund' as const,
        label: 'Dinero de la cuenta',
        disabled: requiresPersonalPayment.value,
    },
    { value: 'member_out_of_pocket' as const, label: 'Dinero personal' },
]);
const createAndAddAnother = ref(false);
const categoryBusy = ref(false);
const { error: conceptError, touch: touchConcept } = useFormFieldInteraction('concept');
const { error: amountError, touch: touchAmount } = useFormFieldInteraction('amount');
const { error: userPaymentsError, touch: touchUserPayments } =
    useFormFieldInteraction('userPayments');
const { error: dateError, touch: touchDate } = useFormFieldInteraction('date');
const { error: financialGoalError, touch: touchFinancialGoal } =
    useFormFieldInteraction('financialGoalId');

const financialGoalOptions = computed(() =>
    props.financialGoals.map((goal) => ({
        value: goal.id,
        label: goal.name,
        description: goal.description ?? undefined,
    })),
);

watch(
    [isSubmitDisabled, isSubmitting, meta, createAndAddAnother, categoryBusy],
    () => {
        emit('stateChange', {
            canSubmit: !isSubmitDisabled.value && !categoryBusy.value,
            isSubmitting: isSubmitting.value,
            keepOpen: props.enableCreateAndAddAnother && createAndAddAnother.value,
        });
    },
    { immediate: true, deep: true },
);

watch(
    () => props.enableCreateAndAddAnother,
    (isEnabled) => {
        if (!isEnabled) {
            createAndAddAnother.value = false;
        }
    },
);

watch(
    () => props.accountUsers,
    (nextUsers) => {
        const firstUserId = nextUsers[0]?.id ?? null;
        const currentAccountUserId =
            nextUsers.find((user) => user.id === props.currentUserId)?.id ?? null;
        const defaultUserId = props.initialValues
            ? firstUserId
            : (currentAccountUserId ?? firstUserId);

        if (!paidByUserId.value && defaultUserId) {
            paidByUserId.value = defaultUserId;
        }

        if (!custodianUserId.value && defaultUserId) {
            custodianUserId.value = defaultUserId;
        }

        if (Object.keys(userPayments.value).length > 0) {
            return;
        }

        userPayments.value = nextUsers.reduce<Record<string, number>>((accumulator, user) => {
            accumulator[user.id] = user.allocationPercentage ?? 0;
            return accumulator;
        }, {});
    },
    { immediate: true },
);

watchEffect(() => {
    if (props.accountUsers.length === 1) {
        const ownerId = props.accountUsers[0]!.id;
        if (paidByUserId.value !== ownerId) paidByUserId.value = ownerId;
        if (custodianUserId.value !== ownerId) custodianUserId.value = ownerId;
        if (paymentSource.value !== 'account_fund') paymentSource.value = 'account_fund';
        if (splitBetweenUsers.value) splitBetweenUsers.value = false;
        return;
    }

    if (isExpense.value && requiresPersonalPayment.value) {
        paymentSource.value = 'member_out_of_pocket';
    }
});

watch(showUserSplitToggle, (isVisible) => {
    if (!isVisible) {
        splitBetweenUsers.value = false;
    }
});

async function handleSubmit(): Promise<void> {
    if (categoryBusy.value || isSubmitting.value) return;
    const payload = await submitForm();

    if (!payload) {
        return;
    }

    const options = {
        keepOpen: props.enableCreateAndAddAnother && createAndAddAnother.value,
    };

    emit('submit', payload);
    emit('submitWithOptions', payload, options);
}
</script>

<template>
    <form :id="props.formId" class="space-y-6" @submit.prevent="handleSubmit">
        <Message v-if="props.serverError" severity="error">{{ props.serverError }}</Message>

        <section class="space-y-4" :style="{ borderColor: 'var(--app-color-border)' }">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <label for="transaction-type" class="text-sm font-medium text-(--app-color-label)">
                    Tipo
                </label>
                <AppToggleButton
                    id="transaction-type"
                    :model-value="type"
                    :options="transactionTypeOptions"
                    @update:model-value="type = $event"
                />
            </div>
        </section>

        <section class="space-y-4">
            <AppInput
                id="transaction-concept"
                v-model="concept"
                label="Concepto"
                placeholder="Ej. Pago a proveedor de logística"
                :error="conceptError"
                @blur="touchConcept"
                required
            />

            <div class="grid gap-4 sm:grid-cols-[minmax(0,1.15fr)_minmax(0,0.85fr)]">
                <div class="contents">
                    <div v-if="isExpense && hasSharedAccount" class="sm:order-1">
                        <AppSearchSelect
                            id="transaction-paid-by"
                            v-model="paidByUserId"
                            label="Pagado por"
                            :options="accountUserOptions"
                            placeholder="Selecciona usuario"
                            search-placeholder="Buscar usuario"
                            empty-message="No hay usuarios disponibles."
                            required
                        />
                    </div>

                    <section
                        v-if="isExpense && hasSharedAccount"
                        class="space-y-3 sm:order-3 sm:col-span-2"
                        :style="{ borderColor: 'var(--app-color-border)' }"
                    >
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div class="space-y-1">
                                <label
                                    for="transaction-payment-source"
                                    class="text-sm font-medium text-(--app-color-label)"
                                >
                                    ¿Con qué dinero se pagó?
                                </label>
                            </div>
                            <AppToggleButton
                                id="transaction-payment-source"
                                :model-value="paymentSource"
                                :options="paymentSourceOptions"
                                @update:model-value="paymentSource = $event"
                            />
                        </div>
                        <AppText v-if="requiresPersonalPayment" size="sm" tone="subtle">
                            La cuenta no tiene saldo disponible. Registra el pago con dinero
                            personal.
                        </AppText>
                    </section>

                    <div class="sm:order-4">
                        <AppInput
                            id="transaction-amount"
                            v-model="amount"
                            label="Cantidad"
                            mask="amount"
                            type="number"
                            inputmode="decimal"
                            min="0"
                            step="0.01"
                            placeholder="$ 00.00"
                            :error="amountError"
                            @blur="touchAmount"
                            required
                        />
                    </div>

                    <TransactionCategorySelect
                        class="sm:order-5"
                        v-model="categoryId"
                        :account-id="accountId"
                        @busy="categoryBusy = $event"
                    />

                    <section
                        v-if="showUserSplitToggle"
                        class="space-y-4 border-t border-(--app-color-border) pt-4 sm:order-6 sm:col-span-2"
                        :style="{ borderColor: 'var(--app-color-border)' }"
                    >
                        <label class="flex items-start gap-3">
                            <Checkbox
                                v-model="splitBetweenUsers"
                                binary
                                class="mt-1"
                                @change="touchUserPayments"
                            />
                            <div class="space-y-1">
                                <span class="block text-sm font-medium text-(--app-color-label)">
                                    Dividir entre usuarios de la cuenta
                                </span>
                                <AppText size="sm">
                                    Usa los porcentajes de participación de los miembros como base y
                                    ajústalos si hace falta.
                                </AppText>
                            </div>
                        </label>

                        <AppPercentageSplitEditor
                            v-if="showUserSplitInputs"
                            :users="props.accountUsers"
                            :model-value="userPayments"
                            @update:model-value="
                                touchUserPayments();
                                userPayments = $event;
                            "
                        />

                        <p v-if="userPaymentsError" class="text-sm text-(--app-color-danger)">
                            {{ userPaymentsError }}
                        </p>
                    </section>
                </div>

                <div
                    class="sm:order-2"
                    :class="{ 'sm:col-span-2': !(isExpense && hasSharedAccount) }"
                >
                    <AppDatePicker
                        id="transaction-date"
                        v-model="date"
                        label="Fecha"
                        placeholder="AAAA-MM-DD"
                        :error="dateError"
                        @change="touchDate"
                        @blur="touchDate"
                        required
                    />
                </div>
            </div>
        </section>

        <section v-if="isIncome" class="space-y-3">
            <AppSearchSelect
                v-if="hasSharedAccount"
                id="transaction-custodian"
                v-model="custodianUserId"
                label="¿Quién recibió el dinero?"
                :options="accountUserOptions"
                placeholder="Selecciona usuario"
                search-placeholder="Buscar usuario"
                empty-message="No hay usuarios disponibles."
                required
            />

            <AppSearchSelect
                id="transaction-financial-goal"
                v-model="financialGoalId"
                label="Meta financiera"
                :options="financialGoalOptions"
                :disabled="props.isLoadingFinancialGoals"
                open-direction="top"
                :placeholder="
                    props.isLoadingFinancialGoals
                        ? 'Cargando metas financieras...'
                        : 'Sin meta financiera'
                "
                search-placeholder="Buscar meta financiera"
                :empty-message="
                    props.isLoadingFinancialGoals
                        ? 'Cargando metas financieras...'
                        : 'No encontramos metas con ese criterio.'
                "
                :error="financialGoalError"
                @change="touchFinancialGoal"
                @blur="touchFinancialGoal"
            />
        </section>

        <section
            v-if="props.enableCreateAndAddAnother"
            class="border-t border-(--app-color-border) pt-4"
            :style="{ borderColor: 'var(--app-color-border)' }"
        >
            <div class="flex items-center justify-between gap-4">
                <div class="min-w-0 space-y-1">
                    <label
                        for="transaction-create-and-add-another"
                        class="block text-sm font-medium text-(--app-color-label)"
                    >
                        Crear y agregar otro
                    </label>
                    <AppText size="sm" tone="subtle">
                        Conserva tipo, fecha, categoría y meta financiera para capturas rápidas.
                    </AppText>
                </div>

                <AppSwitch
                    id="transaction-create-and-add-another"
                    v-model="createAndAddAnother"
                    aria-label="Crear y agregar otra transacción"
                />
            </div>
        </section>
    </form>
</template>
