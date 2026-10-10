<script setup lang="ts">
import Message from 'primevue/message';
import { computed, watch } from 'vue';

import { useSubscriptionForm } from '@/modules/subscriptions/composables/useSubscriptionForm';
import type { Subscription, SubscriptionWritePayload } from '@/modules/subscriptions/types';
import {
    AppDatePicker,
    AppInput,
    AppSearchSelect,
    AppText,
    AppToggleButton,
} from '@/modules/shared/components';
import { useFormFieldInteraction } from '@/modules/shared/composables/useFormFieldInteraction';

type FormState = {
    canSubmit: boolean;
    isSubmitting: boolean;
};

const props = withDefaults(
    defineProps<{
        formId?: string;
        initialValues?: Partial<Subscription> | null;
        fundingAccountOptions?: ReadonlyArray<{
            value: string;
            label: string;
            description?: string;
        }>;
        serverError?: string | null;
    }>(),
    {
        formId: 'subscription-form',
        initialValues: null,
        fundingAccountOptions: () => [],
        serverError: null,
    },
);

const emit = defineEmits<{
    submit: [payload: SubscriptionWritePayload];
    stateChange: [payload: FormState];
}>();

const frequencyUnitOptions = [
    { value: 'days', label: 'Días' },
    { value: 'months', label: 'Meses' },
    { value: 'years', label: 'Años' },
] as const;

const {
    name,
    amount,
    startDate,
    frequencyEvery,
    frequencyUnit,
    cancellationDate,
    fundingAccountId,
    isSubmitting,
    isSubmitDisabled,
    meta,
    submitForm,
} = useSubscriptionForm({
    initialValues: () => props.initialValues,
});
const { error: nameError, touch: touchName } = useFormFieldInteraction('name');
const { error: amountError, touch: touchAmount } = useFormFieldInteraction('amount');
const { error: startDateError, touch: touchStartDate } = useFormFieldInteraction('startDate');
const { error: frequencyUnitError, touch: touchFrequencyUnit } =
    useFormFieldInteraction('frequencyUnit');
const { error: fundingAccountError, touch: touchFundingAccount } =
    useFormFieldInteraction('fundingAccountId');

const fundingAccountSelectOptions = computed(() => props.fundingAccountOptions);

watch(
    [isSubmitDisabled, isSubmitting, meta],
    () => {
        emit('stateChange', {
            canSubmit: !isSubmitDisabled.value,
            isSubmitting: isSubmitting.value,
        });
    },
    { immediate: true, deep: true },
);

async function handleSubmit(): Promise<void> {
    const payload = await submitForm();

    if (!payload) {
        return;
    }

    emit('submit', payload);
}
</script>

<template>
    <form :id="props.formId" class="space-y-4 sm:space-y-6" @submit.prevent="handleSubmit">
        <Message v-if="props.serverError" severity="error">{{ props.serverError }}</Message>

        <section class="space-y-4 sm:space-y-5">
            <AppInput
                id="subscription-name"
                v-model="name"
                label="Nombre"
                placeholder="Ej. Plan premium anual"
                :error="nameError"
                @blur="touchName"
                required
            />

            <div class="grid gap-4 sm:grid-cols-2">
                <AppInput
                    id="subscription-amount"
                    v-model="amount"
                    label="Cantidad"
                    mask="amount"
                    type="number"
                    inputmode="decimal"
                    min="0"
                    step="0.01"
                    placeholder="0.00"
                    :error="amountError"
                    @blur="touchAmount"
                    required
                />

                <AppDatePicker
                    id="subscription-start-date"
                    v-model="startDate"
                    label="Fecha de contratación"
                    placeholder="AAAA-MM-DD"
                    :error="startDateError"
                    @change="touchStartDate"
                    @blur="touchStartDate"
                    required
                />
            </div>
        </section>

        <section
            class="space-y-4 border-y py-4 sm:rounded-xl sm:border sm:bg-(--app-color-surface-muted) sm:px-4"
            :style="{ borderColor: 'var(--app-color-border)' }"
        >
            <div class="space-y-1">
                <p class="text-sm font-semibold text-(--app-color-text)">Frecuencia</p>
                <AppText size="sm">Define cada cuánto debe programarse el siguiente cargo.</AppText>
            </div>

            <div class="grid gap-4 sm:grid-cols-[minmax(0,0.9fr)_minmax(0,1.1fr)]">
                <AppInput
                    id="subscription-frequency-every"
                    v-model="frequencyEvery"
                    label="Cada"
                    type="number"
                    inputmode="numeric"
                    min="1"
                    step="1"
                    placeholder="1"
                    :error="frequencyUnitError"
                    @blur="touchFrequencyUnit"
                    required
                />

                <div class="space-y-2.5">
                    <div class="flex min-h-5 items-center">
                        <label
                            for="subscription-frequency-unit"
                            class="text-sm font-medium text-(--app-color-label)"
                        >
                            Unidad
                        </label>
                    </div>

                    <AppToggleButton
                        id="subscription-frequency-unit"
                        v-model="frequencyUnit"
                        :options="frequencyUnitOptions"
                    />
                </div>
            </div>
        </section>

        <section class="space-y-4 sm:space-y-5">
            <AppDatePicker
                id="subscription-finished-at"
                v-model="cancellationDate"
                label="Fecha de cancelación"
                placeholder="Sin cancelación"
                clearable
            />

            <AppSearchSelect
                id="subscription-funding-account"
                v-model="fundingAccountId"
                label="Cuenta de alimentación"
                :options="fundingAccountSelectOptions"
                placeholder="Selecciona una cuenta"
                search-placeholder="Buscar cuenta"
                empty-message="No encontramos cuentas disponibles."
                :error="fundingAccountError"
                @change="touchFundingAccount"
                @blur="touchFundingAccount"
            />
        </section>
    </form>
</template>
