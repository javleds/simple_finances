<script setup lang="ts">
import { computed, reactive, watch } from 'vue';

import { AppInput, AppText, AppToggleButton } from '@/modules/shared/components';

type YesNoValue = 'yes' | 'no';

type AccountFormSubmit = {
  name: string;
  color: string | null;
  description: string;
  isVirtual: boolean;
  isCredit: boolean;
  creditLine: number | null;
  closingDay: number | null;
};

const props = withDefaults(
  defineProps<{
    formId?: string;
    initialValues?: Partial<AccountFormSubmit> | null;
  }>(),
  {
    formId: 'account-form',
    initialValues: null,
  },
);

const emit = defineEmits<{
  submit: [payload: AccountFormSubmit];
}>();

const yesNoOptions = [
  { value: 'yes', label: 'Sí' },
  { value: 'no', label: 'No' },
] as const;

const state = reactive({
  name: '',
  color: '',
  description: '',
  isVirtual: 'no' as YesNoValue,
  isCredit: 'no' as YesNoValue,
  creditLine: null as number | null,
  closingDay: null as number | null,
});

const showCreditFields = computed(() => state.isCredit === 'yes');

watch(
  () => props.initialValues,
  (nextValues) => {
    state.name = nextValues?.name ?? '';
    state.color = nextValues?.color ?? '';
    state.description = nextValues?.description ?? '';
    state.isVirtual = nextValues?.isVirtual ? 'yes' : 'no';
    state.isCredit = nextValues?.isCredit ? 'yes' : 'no';
    state.creditLine = nextValues?.creditLine ?? null;
    state.closingDay = nextValues?.closingDay ?? null;
  },
  { immediate: true },
);

watch(showCreditFields, (isVisible) => {
  if (isVisible) {
    return;
  }

  state.creditLine = null;
  state.closingDay = null;
});

function updateCreditLine(event: Event): void {
  const nextValue = Number((event.target as HTMLInputElement).value);

  if (Number.isNaN(nextValue)) {
    state.creditLine = null;
    return;
  }

  state.creditLine = nextValue;
}

function updateClosingDay(event: Event): void {
  const nextValue = Number((event.target as HTMLInputElement).value);

  if (Number.isNaN(nextValue)) {
    state.closingDay = null;
    return;
  }

  state.closingDay = nextValue;
}

function submitForm(): void {
  emit('submit', {
    name: state.name.trim(),
    color: state.color.trim() ? state.color : null,
    description: state.description.trim(),
    isVirtual: state.isVirtual === 'yes',
    isCredit: state.isCredit === 'yes',
    creditLine: showCreditFields.value ? state.creditLine : null,
    closingDay: showCreditFields.value ? state.closingDay : null,
  });
}
</script>

<template>
  <form :id="props.formId" class="space-y-6" @submit.prevent="submitForm">
    <section class="space-y-5">
      <AppInput
        id="account-name"
        v-model="state.name"
        label="Nombre"
        placeholder="Ej. Cuenta operativa regional"
        required
      />

      <div class="grid gap-4 sm:grid-cols-[minmax(0,1fr)_9rem]">
        <div class="space-y-2.5">
          <div class="flex min-h-5 items-center">
            <label for="account-description" class="text-sm font-medium text-(--app-color-label)">
              Descripción
            </label>
          </div>

          <textarea
            id="account-description"
            v-model="state.description"
            rows="4"
            placeholder="Describe el propósito y contexto de la cuenta"
            class="w-full rounded-lg border border-(--app-color-input-border) bg-(--app-color-input-bg) px-4 py-3 text-sm text-(--app-color-input-text) transition outline-none placeholder:text-(--app-color-input-placeholder) focus:border-(--app-color-primary) focus:ring-4 focus:ring-(--app-color-focus-ring)"
          />
        </div>

        <div class="space-y-2.5">
          <div class="flex min-h-5 items-center">
            <label for="account-color" class="text-sm font-medium text-(--app-color-label)">
              Color
            </label>
          </div>

          <div
            class="flex h-12 items-center gap-3 rounded-lg border border-(--app-color-input-border) bg-(--app-color-input-bg) px-3"
          >
            <input
              id="account-color"
              v-model="state.color"
              type="color"
              class="h-7 w-10 cursor-pointer rounded border-0 bg-transparent p-0"
            />
            <span class="truncate text-sm text-(--app-color-text-subtle)">
              {{ state.color || 'Opcional' }}
            </span>
          </div>
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
            :model-value="state.isVirtual"
            :options="yesNoOptions"
            @update:model-value="state.isVirtual = $event as YesNoValue"
          />
        </div>

        <div class="space-y-2">
          <label for="account-is-credit" class="text-sm font-medium text-(--app-color-label)">
            Es de crédito
          </label>
          <AppToggleButton
            id="account-is-credit"
            :model-value="state.isCredit"
            :options="yesNoOptions"
            @update:model-value="state.isCredit = $event as YesNoValue"
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
          :model-value="state.creditLine ?? ''"
          label="Línea de crédito"
          type="number"
          inputmode="decimal"
          min="0"
          step="0.01"
          placeholder="0.00"
          :required="showCreditFields"
          @input="updateCreditLine"
        />

        <AppInput
          id="account-closing-day"
          :model-value="state.closingDay ?? ''"
          label="Día de corte"
          type="number"
          inputmode="numeric"
          min="1"
          max="31"
          step="1"
          placeholder="1 - 31"
          :required="showCreditFields"
          @input="updateClosingDay"
        />
      </div>
    </section>
  </form>
</template>
