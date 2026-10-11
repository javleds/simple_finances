<script setup lang="ts">
import { computed, watch } from 'vue';
import { createPersonalMoneySettingsSchema } from '../schemas';
import {
    AppModal,
    AppDatePicker,
    AppInput,
    AppSearchSelect,
    AppText,
} from '@/modules/shared/components';
import { usePrimeForm } from '@/modules/shared/composables/usePrimeForm';
import { usePersonalMoney } from '../composables/usePersonalMoney';
import type { AppModalAction } from '@/modules/shared/types/modal';

const { state, mutationError, isSaving, saveSettings } = usePersonalMoney();
const props = defineProps<{ open: boolean }>();
const emit = defineEmits<{ close: [] }>();
const today = new Date();
const localDate = `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}-${String(today.getDate()).padStart(2, '0')}`;
const schema = createPersonalMoneySettingsSchema(localDate);
const form = usePrimeForm({
    schema,
    initialValues: { accountId: '', startedAt: localDate, openingBalance: '0' },
});
const accountOptions = computed(
    () =>
        state.value?.accounts.map((account) => ({
            value: String(account.id),
            label: account.name,
        })) ?? [],
);
const enabled = computed(() => state.value?.settings?.enabled === true);
function openSettings(): void {
    const settings = state.value?.settings;
    form.resetForm({
        values: {
            accountId: settings ? String(settings.account_id) : '',
            startedAt: settings?.started_at ?? localDate,
            openingBalance: String(settings?.opening_balance ?? 0),
        },
    });
}
const submit = form.handleSubmit(async (values) => {
    if (isSaving.value) return;
    try {
        await saveSettings({
            account_id: Number(values.accountId),
            started_at: values.startedAt,
            opening_balance: Number(values.openingBalance),
            enabled: true,
        });
        emit('close');
    } catch {
        return;
    }
});
async function disable(): Promise<void> {
    if (isSaving.value || !state.value?.settings) return;
    try {
        await saveSettings({ ...state.value.settings, enabled: false });
        emit('close');
    } catch {
        return;
    }
}
watch(
    () => props.open,
    (open) => {
        if (open) openSettings();
    },
    { immediate: true },
);
const actions = computed<ReadonlyArray<AppModalAction>>(() => [
    {
        key: 'cancel',
        label: 'Cancelar',
        tone: 'neutral',
        disabled: isSaving.value,
        autoClose: true,
    },
    ...(enabled.value
        ? [
              {
                  key: 'disable',
                  label: 'Desactivar cálculo',
                  tone: 'danger' as const,
                  disabled: isSaving.value,
              },
          ]
        : []),
    {
        key: 'save',
        label: 'Guardar',
        type: 'submit',
        form: 'personal-money-settings',
        loading: isSaving.value,
        disabled: !accountOptions.value.length,
    },
]);
async function handleAction(key: string): Promise<void> {
    if (key === 'disable') await disable();
}
</script>

<template>
    <AppModal
        :open="open"
        title="Configurar mi disponible"
        :actions="actions"
        @close="!isSaving && emit('close')"
        @action="handleAction"
    >
        <p v-if="mutationError" role="alert" class="mb-4 text-(--app-color-danger)">
            {{ mutationError }}
        </p>
        <form id="personal-money-settings" class="grid gap-4" @submit.prevent="submit">
            <AppText
                >Elige una cuenta individual para tus ingresos y gastos personales. Indica el saldo
                libre al inicio de la fecha elegida, antes de sus movimientos, excluyendo el dinero
                que ya apartaste. Se incluyen todos los movimientos de esa fecha en
                adelante.</AppText
            >
            <AppSearchSelect
                id="personal-money-account"
                label="Cuenta personal"
                :options="accountOptions"
                :model-value="form.values.accountId"
                :error="form.errors.value.accountId"
                :disabled="isSaving"
                @update:model-value="form.setFieldValue('accountId', $event ?? '')"
            />
            <AppText v-if="!accountOptions.length"
                >Primero crea una cuenta individual para calcular tu disponible.</AppText
            >
            <AppDatePicker
                id="personal-money-start"
                label="Fecha inicial"
                :max-date="localDate"
                :model-value="form.values.startedAt"
                :error="form.errors.value.startedAt"
                :disabled="isSaving"
                @update:model-value="form.setFieldValue('startedAt', $event ?? '')"
            />
            <AppInput
                id="personal-money-opening"
                label="Saldo libre al inicio de la fecha elegida"
                type="number"
                step="0.01"
                :model-value="form.values.openingBalance"
                :error="form.errors.value.openingBalance"
                :disabled="isSaving"
                @update:model-value="form.setFieldValue('openingBalance', $event)"
            />
        </form>
    </AppModal>
</template>
