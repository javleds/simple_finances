<script setup lang="ts">
import { computed, watch } from 'vue';

import { useSubscriptionForm } from '@/modules/subscriptions/composables/useSubscriptionForm';
import type { Subscription, SubscriptionWritePayload } from '@/modules/subscriptions/types';
import {
  AppDatePicker,
  AppInput,
  AppSearchSelect,
  AppText,
} from '@/modules/shared/components';

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
  { value: 'day', label: 'Día' },
  { value: 'week', label: 'Semana' },
  { value: 'month', label: 'Mes' },
  { value: 'year', label: 'Año' },
] as const;

const {
  name,
  amount,
  startDate,
  frequencyEvery,
  frequencyUnit,
  cancellationDate,
  fundingAccountId,
  errors,
  isSubmitting,
  isSubmitDisabled,
  meta,
  submitForm,
} = useSubscriptionForm({
  initialValues: () => props.initialValues,
});

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
  <form :id="props.formId" class="space-y-6" @submit.prevent="handleSubmit">
    <section v-if="props.serverError" class="rounded-xl border border-(--app-color-danger) px-4 py-3">
      <AppText size="sm" class="text-(--app-color-danger)!">
        {{ props.serverError }}
      </AppText>
    </section>

    <section class="space-y-5">
      <AppInput
        id="subscription-name"
        v-model="name"
        label="Nombre"
        placeholder="Ej. Plan premium anual"
        :error="errors.name"
        required
      />

      <div class="grid gap-4 sm:grid-cols-2">
        <AppInput
          id="subscription-amount"
          v-model="amount"
          label="Cantidad"
          type="number"
          inputmode="decimal"
          min="0"
          step="0.01"
          placeholder="0.00"
          :error="errors.amount"
          required
        />

        <AppDatePicker
          id="subscription-start-date"
          v-model="startDate"
          label="Fecha de contratación"
          placeholder="AAAA-MM-DD"
          :error="errors.startDate"
          required
        />
      </div>
    </section>

    <section
      class="space-y-4 rounded-xl border bg-(--app-color-surface-muted) px-4 py-4"
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
          :error="errors.frequencyEvery"
          required
        />

        <div class="space-y-2.5">
          <div class="flex min-h-5 items-center">
            <label for="subscription-frequency-unit" class="text-sm font-medium text-(--app-color-label)">
              Unidad
            </label>
          </div>

          <div class="grid grid-cols-2 gap-2">
            <button
              v-for="option in frequencyUnitOptions"
              :key="option.value"
              type="button"
              class="rounded-lg border px-3 py-2 text-sm font-medium transition focus:ring-4 focus:ring-(--app-color-focus-ring) focus:outline-none"
              :class="
                frequencyUnit === option.value
                  ? 'border-(--app-color-primary) bg-(--app-color-primary) text-(--app-color-primary-foreground)'
                  : 'border-(--app-color-input-border) bg-(--app-color-input-bg) text-(--app-color-text)'
              "
              @click="frequencyUnit = option.value"
            >
              {{ option.label }}
            </button>
          </div>
        </div>
      </div>
    </section>

    <section class="space-y-5">
      <AppDatePicker
        id="subscription-cancellation-date"
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
      />
    </section>
  </form>
</template>
