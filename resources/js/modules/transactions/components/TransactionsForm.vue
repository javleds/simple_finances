<script setup lang="ts">
import Message from 'primevue/message';
import { usePersonalMoney } from '@/modules/personal-money/composables/usePersonalMoney';
import type { PersonalMoneyResolution } from '@/modules/personal-money/types';
import TransactionCategorySelect from './TransactionCategorySelect.vue';
import TransactionAttachments from './TransactionAttachments.vue';
import Checkbox from 'primevue/checkbox';
import { computed, reactive, ref, watch, watchEffect } from 'vue';

import type { AccountMember } from '@/modules/accounts/types';
import { useTransactionForm } from '@/modules/transactions/composables/useTransactionForm';
import type {
    AttachmentDraft,
    Transaction,
    TransactionSubmitOptions,
    TransactionWritePayload,
} from '@/modules/transactions/types';
import {
    AppButton,
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
    savedTransactionId?: string | null;
};

const props = withDefaults(
    defineProps<{
        formId?: string;
        saving?: boolean;
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
        saving: false,
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
    lockedAccountId: () => props.lockedAccountId,
});

const isEditingOriginalAccount = computed(
    () =>
        Boolean(props.initialValues?.id) &&
        accountId.value === (props.initialValues?.accountId ?? props.lockedAccountId),
);
const preservesLegacyFinancialChoices = computed(
    () => isEditingOriginalAccount.value && props.initialValues?.usesSharedAccountRules == null,
);
const hasSharedAccount = computed(() => {
    const snapshot = props.initialValues?.usesSharedAccountRules;
    if (isEditingOriginalAccount.value && typeof snapshot === 'boolean') return snapshot;
    return props.accountUsers.length > 1;
});
const showUserSplitToggle = computed(() => isExpense.value && hasSharedAccount.value);
const showUserSplitInputs = computed(() => showUserSplitToggle.value && splitBetweenUsers.value);
function participantOptions(
    role: 'payer' | 'custodian',
): Array<{ value: string; label: string; description: string }> {
    const options = props.accountUsers.map((user) => ({
        value: user.id,
        label: user.name,
        description: user.email,
    }));
    if (!isEditingOriginalAccount.value) return options;
    const id =
        role === 'payer' ? props.initialValues?.paidByUserId : props.initialValues?.custodianUserId;
    const name =
        role === 'payer'
            ? props.initialValues?.paidByUserName
            : props.initialValues?.custodianUserName;
    if (id && !options.some((option) => option.value === id))
        options.push({
            value: id,
            label: name ?? 'Miembro anterior',
            description: 'Participó en este movimiento',
        });
    return options;
}
const payerOptions = computed(() => participantOptions('payer'));
const custodianOptions = computed(() => participantOptions('custodian'));
const splitUsers = computed(() => {
    const users: Array<{ id: string; name: string }> = props.accountUsers.map((user) => ({
        id: user.id,
        name: user.name,
    }));
    if (!isEditingOriginalAccount.value) return users;
    const previous = props.initialValues;
    for (const id of Object.keys(previous?.userPayments ?? {})) {
        if (users.some((user) => user.id === id)) continue;
        const name =
            id === previous?.paidByUserId
                ? previous.paidByUserName
                : id === previous?.custodianUserId
                  ? previous.custodianUserName
                  : id === previous?.creatorId
                    ? previous.creatorName
                    : null;
        users.push({ id, name: name ?? 'Miembro anterior' });
    }
    return users;
});
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
const { state: personalMoney } = usePersonalMoney();
const cashSource = ref<PersonalMoneyResolution | null>(null);
const showCashSource = computed(
    () =>
        personalMoney.value?.settings?.enabled === true &&
        personalMoney.value.account_available !== false &&
        personalMoney.value.available_balance !== null &&
        hasSharedAccount.value &&
        (isIncome.value ||
            (paymentSource.value === 'member_out_of_pocket' &&
                paidByUserId.value === props.currentUserId)),
);
const cashSourceOptions = [
    { value: 'personal', label: 'Mi dinero disponible' },
    { value: 'external', label: 'Origen externo' },
    { value: 'pending', label: 'Revisar después' },
];
watch([() => props.initialValues, accountId, type, paidByUserId, paymentSource], () => {
    cashSource.value = null;
});
function updateCashSource(value: string | null): void {
    cashSource.value =
        value === 'personal' || value === 'external' || value === 'pending' ? value : null;
}
const createAndAddAnother = ref(false);
const categoryBusy = ref(false);
const attachments = reactive<AttachmentDraft>({ files: [], transactionId: null });
watch(
    () => props.initialValues,
    (nextValues, previousValues) => {
        if (nextValues?.id && nextValues.id === previousValues?.id) return;
        attachments.files = [];
        attachments.transactionId = null;
    },
);
function confirmClose(): boolean {
    if (props.saving) return false;
    return (
        !attachments.files.some((entry) => !entry.uploaded) ||
        window.confirm('Hay comprobantes sin subir. ¿Salir y descartarlos?')
    );
}
defineExpose({ confirmClose });
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
    [
        isSubmitDisabled,
        isSubmitting,
        meta,
        createAndAddAnother,
        categoryBusy,
        () => attachments.transactionId,
    ],
    () => {
        emit('stateChange', {
            canSubmit: !isSubmitDisabled.value && !categoryBusy.value,
            isSubmitting: isSubmitting.value,
            keepOpen: props.enableCreateAndAddAnother && createAndAddAnother.value,
            savedTransactionId: attachments.transactionId,
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
    if (preservesLegacyFinancialChoices.value) return;
    if (isEditingOriginalAccount.value && props.initialValues?.usesSharedAccountRules === false) {
        if (paymentSource.value !== 'account_fund') paymentSource.value = 'account_fund';
        if (splitBetweenUsers.value) splitBetweenUsers.value = false;
        return;
    }
    if (!hasSharedAccount.value && props.accountUsers.length === 1) {
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
    if (!isVisible && !preservesLegacyFinancialChoices.value) {
        splitBetweenUsers.value = false;
    }
});

async function continueWithoutFiles(): Promise<void> {
    if (props.saving || !window.confirm('¿Continuar sin subir los archivos pendientes?')) return;
    attachments.files = attachments.files.filter((entry) => entry.uploaded);
    await handleSubmit();
}

async function handleSubmit(): Promise<void> {
    if (categoryBusy.value || isSubmitting.value || props.saving) return;
    const payload = await submitForm();

    if (!payload) {
        return;
    }

    const options = {
        keepOpen: props.enableCreateAndAddAnother && createAndAddAnother.value,
    };

    if (showCashSource.value && cashSource.value) payload.cashSource = cashSource.value;
    payload.attachments = attachments;
    emit('submit', payload);
    emit('submitWithOptions', payload, options);
}
</script>

<template>
    <form :id="props.formId" class="space-y-6" @submit.prevent="handleSubmit">
        <Message v-if="props.serverError" severity="error">{{ props.serverError }}</Message>

        <section
            :inert="attachments.transactionId ? true : undefined"
            class="space-y-4"
            :style="{ borderColor: 'var(--app-color-border)' }"
        >
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

        <AppText
            v-if="
                isEditingOriginalAccount &&
                props.initialValues?.usesSharedAccountRules === true &&
                props.accountUsers.length <= 1
            "
            size="sm"
            tone="subtle"
            >Este movimiento conserva las reglas de cuando la cuenta era compartida.</AppText
        >
        <section :inert="attachments.transactionId ? true : undefined" class="space-y-4">
            <AppInput
                id="transaction-concept"
                v-model="concept"
                label="Concepto"
                placeholder="Ej. Pago a proveedor de logística"
                :error="conceptError"
                @blur="touchConcept"
                required
            />

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
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

                <div>
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

            <TransactionCategorySelect
                v-model="categoryId"
                :account-id="accountId"
                :transaction-type="type"
                @busy="categoryBusy = $event"
            />
        </section>
        <TransactionAttachments
            :transaction-id="props.initialValues?.id"
            :draft="attachments"
            @files-change="attachments.files = $event"
            :busy="isSubmitting || props.saving"
        />
        <section v-if="attachments.transactionId" class="space-y-2">
            <AppText size="sm" tone="subtle"
                >Movimiento guardado. Puedes reintentar los comprobantes sin crear otro
                movimiento.</AppText
            >
            <div class="flex flex-wrap gap-2">
                <AppButton type="submit" :disabled="props.saving"
                    >Reintentar comprobantes</AppButton
                >
                <AppButton
                    type="button"
                    variant="ghost"
                    :disabled="props.saving"
                    @click="continueWithoutFiles"
                    >Continuar sin los archivos pendientes</AppButton
                >
            </div>
        </section>
        <section :inert="attachments.transactionId ? true : undefined" class="space-y-4">
            <div v-if="isExpense && hasSharedAccount">
                <AppSearchSelect
                    id="transaction-paid-by"
                    v-model="paidByUserId"
                    label="Pagado por"
                    :options="payerOptions"
                    placeholder="Selecciona usuario"
                    search-placeholder="Buscar usuario"
                    empty-message="No hay usuarios disponibles."
                    required
                />
            </div>

            <section
                v-if="isExpense && hasSharedAccount"
                class="space-y-3"
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
                    La cuenta no tiene saldo disponible. Registra el pago con dinero personal.
                </AppText>
            </section>

            <section
                v-if="showUserSplitToggle"
                class="space-y-4 border-t border-(--app-color-border) pt-4"
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
                    :users="splitUsers"
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
        </section>

        <section
            v-if="isIncome"
            :inert="attachments.transactionId ? true : undefined"
            class="space-y-3"
        >
            <AppSearchSelect
                v-if="hasSharedAccount"
                id="transaction-custodian"
                v-model="custodianUserId"
                label="¿Quién recibió el dinero?"
                :options="custodianOptions"
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

        <div
            v-if="showCashSource"
            :inert="attachments.transactionId ? true : undefined"
            class="space-y-2"
        >
            <AppSearchSelect
                id="transaction-cash-source"
                label="Origen de tu dinero (privado)"
                :model-value="cashSource"
                :options="cashSourceOptions"
                placeholder="Conservar origen o revisar después"
                :disabled="props.saving"
                @update:model-value="updateCashSource"
            />
            <AppText
                >Elige si este movimiento afecta tu disponible personal. Solo tú puedes ver esta
                elección.</AppText
            >
        </div>

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
