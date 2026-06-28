<script setup lang="ts">
import { computed, watch } from 'vue';

import { useAccountForm } from '@/modules/accounts/composables/useAccountForm';
import type { Account, AccountWritePayload } from '@/modules/accounts/types';
import { AppInput, AppText, AppToggleButton } from '@/modules/shared/components';
import { useFormFieldInteraction } from '@/modules/shared/composables/useFormFieldInteraction';

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
  isSubmitting,
  isSubmitDisabled,
  meta,
  showCreditFields,
  submitForm,
} = useAccountForm({
  initialValues: () => props.initialValues,
});

const { error: nameError, touch: touchName } = useFormFieldInteraction('name');
const { error: descriptionError, touch: touchDescription } = useFormFieldInteraction('description');
const { error: colorError, touch: touchColor } = useFormFieldInteraction('color');
const { error: creditLineError, touch: touchCreditLine } = useFormFieldInteraction('creditLine');
const { error: closingDayError, touch: touchClosingDay } = useFormFieldInteraction('closingDay');

const creditConfigLabelHasError = computed(() => {
  return Boolean(creditLineError.value || closingDayError.value);
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
    <section
      v-if="props.serverError"
      class="rounded-xl border border-(--app-color-danger) px-4 py-3"
    >
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
        :error="nameError"
        @blur="touchName"
        required
      />

      <div class="grid gap-4 sm:grid-cols-[minmax(0,1fr)_9rem]">
        <div class="space-y-2.5">
          <div class="flex min-h-5 items-center">
            <label
              for="account-description"
              class="text-sm font-medium"
              :class="descriptionError ? 'text-(--app-color-danger)' : 'text-(--app-color-label)'"
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
              descriptionError
                ? 'border-(--app-color-danger) focus:border-(--app-color-danger)'
                : 'border-(--app-color-input-border) focus:border-(--app-color-primary)'
            "
            :aria-invalid="Boolean(descriptionError)"
            @blur="touchDescription"
          />

          <p v-if="descriptionError" class="text-sm text-(--app-color-danger)">
            {{ descriptionError }}
          </p>
        </div>

        <div class="space-y-2.5">
          <div class="flex min-h-5 items-center">
            <label
              for="account-color"
              class="text-sm font-medium"
              :class="colorError ? 'text-(--app-color-danger)' : 'text-(--app-color-label)'"
            >
              Color
            </label>
          </div>

          <div
            class="flex h-12 items-center gap-3 rounded-lg border bg-(--app-color-input-bg) px-3"
            :class="
              colorError ? 'border-(--app-color-danger)' : 'border-(--app-color-input-border)'
            "
          >
            <input
              id="account-color"
              v-model="color"
              type="color"
              class="h-7 w-10 cursor-pointer rounded border-0 bg-transparent p-0"
              :aria-invalid="Boolean(colorError)"
              @blur="touchColor"
            />
            <span class="truncate text-sm text-(--app-color-text-subtle)">
              {{ color || 'Opcional' }}
            </span>
          </div>

          <p v-if="colorError" class="text-sm text-(--app-color-danger)">
            {{ colorError }}
          </p>
        </div>
      </div>
    </section>

    <section
      class="space-y-4 rounded-xl border bg-(--app-color-surface-muted) px-4 py-4"
      :style="{ borderColor: 'var(--app-color-border)' }"
    >
      <div class="grid gap-4 sm:grid-cols-2">
        <div class="flex w-full items-center justify-between">
          <label for="account-is-virtual" class="text-sm font-medium text-(--app-color-label)">
            Es virtual
          </label>
          <AppToggleButton
            id="account-is-virtual"
            :model-value="isVirtual"
            :options="yesNoOptions"
            @update:model-value="isVirtual = $event"
          />
        </div>

        <div class="flex w-full items-center justify-between">
          <label
            for="account-is-credit"
            class="text-sm font-medium"
            :class="
              creditConfigLabelHasError ? 'text-(--app-color-danger)' : 'text-(--app-color-label)'
            "
          >
            Es de crédito
          </label>
          <AppToggleButton
            id="account-is-credit"
            :model-value="isCredit"
            :options="yesNoOptions"
            @update:model-value="isCredit = $event"
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
          mask="amount"
          type="number"
          inputmode="decimal"
          min="0"
          step="0.01"
          placeholder="0.00"
          :error="creditLineError"
          @blur="touchCreditLine"
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
          :error="closingDayError"
          @blur="touchClosingDay"
          :required="showCreditFields"
        />
      </div>
    </section>
  </form>
</template>
