<script setup lang="ts">
import { watch } from 'vue';

import { useAccountForm } from '@/modules/accounts/composables/useAccountForm';
import type { Account, AccountWritePayload } from '@/modules/accounts/types';
import { AppInput, AppText, AppToggleButton } from '@/modules/shared/components';

type YesNoValue = 'yes' | 'no';

type FormState = {
  canSubmit: boolean;
  isSubmitting: boolean;
};

const props = withDefaults(
  defineProps<{
    formId?: string;
    initialValues?: Partial<Account> | null;
    serverError?: string | null;
  }>(),
  {
    formId: 'account-form',
    initialValues: null,
    serverError: null,
  },
);

const emit = defineEmits<{
  submit: [payload: AccountWritePayload];
  stateChange: [payload: FormState];
}>();

const yesNoOptions = [
  { value: 'yes', label: 'Sí' },
  { value: 'no', label: 'No' },
] as const;

const {
  name,
  color,
  description,
  isVirtual,
  isCredit,
  creditLine,
  closingDay,
  errors,
  isSubmitting,
  isSubmitDisabled,
  meta,
  showCreditFields,
  submitForm,
} = useAccountForm({
  initialValues: () => props.initialValues,
});

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
        id="account-name"
        v-model="name"
        label="Nombre"
        placeholder="Ej. Cuenta operativa regional"
        :error="errors.name"
        required
      />

      <div class="grid gap-4 sm:grid-cols-[minmax(0,1fr)_9rem]">
        <div class="space-y-2.5">
          <div class="flex min-h-5 items-center">
            <label
              for="account-description"
              class="text-sm font-medium"
              :class="errors.description ? 'text-(--app-color-danger)' : 'text-(--app-color-label)'"
            >
              Descripción
            </label>
          </div>

          <textarea
            id="account-description"
            v-model="description"
            rows="4"
            placeholder="Describe el propósito y contexto de la cuenta"
            class="w-full rounded-lg border bg-(--app-color-input-bg) px-4 py-3 text-sm text-(--app-color-input-text) transition outline-none placeholder:text-(--app-color-input-placeholder) focus:ring-4 focus:ring-(--app-color-focus-ring)"
            :class="
              errors.description
                ? 'border-(--app-color-danger) focus:border-(--app-color-danger)'
                : 'border-(--app-color-input-border) focus:border-(--app-color-primary)'
            "
            :aria-invalid="Boolean(errors.description)"
          />

          <p v-if="errors.description" class="text-sm text-(--app-color-danger)">
            {{ errors.description }}
          </p>
        </div>

        <div class="space-y-2.5">
          <div class="flex min-h-5 items-center">
            <label
              for="account-color"
              class="text-sm font-medium"
              :class="errors.color ? 'text-(--app-color-danger)' : 'text-(--app-color-label)'"
            >
              Color
            </label>
          </div>

          <div
            class="flex h-12 items-center gap-3 rounded-lg border bg-(--app-color-input-bg) px-3"
            :class="errors.color ? 'border-(--app-color-danger)' : 'border-(--app-color-input-border)'"
          >
            <input
              id="account-color"
              v-model="color"
              type="color"
              class="h-7 w-10 cursor-pointer rounded border-0 bg-transparent p-0"
            />
            <span class="truncate text-sm text-(--app-color-text-subtle)">
              {{ color || 'Opcional' }}
            </span>
          </div>

          <p v-if="errors.color" class="text-sm text-(--app-color-danger)">
            {{ errors.color }}
          </p>
        </div>
      </div>
    </section>

    <section
      class="space-y-4 rounded-xl border bg-(--app-color-surface-muted) px-4 py-4"
      :style="{ borderColor: 'var(--app-color-border)' }"
    >
      <div class="grid gap-4 sm:grid-cols-2">
        <div class="space-y-2">
          <label for="account-is-virtual" class="text-sm font-medium text-(--app-color-label)">
            Es virtual
          </label>
          <AppToggleButton
            id="account-is-virtual"
            :model-value="isVirtual"
            :options="yesNoOptions"
            @update:model-value="isVirtual = $event as YesNoValue"
          />
        </div>

        <div class="space-y-2">
          <label
            for="account-is-credit"
            class="text-sm font-medium"
            :class="errors.creditLine || errors.closingDay ? 'text-(--app-color-danger)' : 'text-(--app-color-label)'"
          >
            Es de crédito
          </label>
          <AppToggleButton
            id="account-is-credit"
            :model-value="isCredit"
            :options="yesNoOptions"
            @update:model-value="isCredit = $event as YesNoValue"
          />
        </div>
      </div>

      <AppText size="sm">
        Define si la cuenta es virtual y si opera bajo una línea de crédito para mostrar los campos
        financieros correspondientes.
      </AppText>
    </section>

    <section
      v-if="showCreditFields"
      class="space-y-4 rounded-xl border px-4 py-4"
      :style="{ borderColor: 'var(--app-color-border)' }"
    >
      <div class="space-y-1">
        <p class="text-sm font-semibold text-(--app-color-text)">Configuración de crédito</p>
        <AppText size="sm">
          Estos campos sólo aplican cuando la cuenta opera con línea de crédito.
        </AppText>
      </div>

      <div class="grid gap-4 sm:grid-cols-2">
        <AppInput
          id="account-credit-line"
          v-model="creditLine"
          label="Línea de crédito"
          type="number"
          inputmode="decimal"
          min="0"
          step="0.01"
          placeholder="0.00"
          :error="errors.creditLine"
          :required="showCreditFields"
        />

        <AppInput
          id="account-closing-day"
          v-model="closingDay"
          label="Día de corte"
          type="number"
          inputmode="numeric"
          min="1"
          max="31"
          step="1"
          placeholder="1 - 31"
          :error="errors.closingDay"
          :required="showCreditFields"
        />
      </div>
    </section>
  </form>
</template>
