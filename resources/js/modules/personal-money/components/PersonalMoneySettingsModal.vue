<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { createPersonalMoneySettingsSchema } from '../schemas';
import {
    AppButton,
    AppModal,
    AppDatePicker,
    AppInput,
    AppSearchSelect,
    AppText,
} from '@/modules/shared/components';
import { usePrimeForm } from '@/modules/shared/composables/usePrimeForm';
import { usePersonalMoney } from '../composables/usePersonalMoney';
import type { PersonalMoneySettingsPayload } from '../types';
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
const startingNow = ref(false);
const operationKey = ref('');
const operationPayload = ref<string | null>(null);
const enabled = computed(() => state.value?.settings?.enabled === true);
function openSettings(): void {
    const settings = state.value?.settings;
    startingNow.value = !settings;
    operationKey.value = crypto.randomUUID();
    operationPayload.value = null;
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
        const fingerprint = JSON.stringify([values.accountId, Number(values.openingBalance)]);
        if (startingNow.value && operationPayload.value !== fingerprint) {
            operationKey.value = crypto.randomUUID();
            operationPayload.value = fingerprint;
        }
        const payload: PersonalMoneySettingsPayload = {
            account_id: Number(values.accountId),
            opening_balance: Number(values.openingBalance),
            enabled: true,
            ...(startingNow.value
                ? {
                      start_mode: 'now' as const,
                      operation_key: operationKey.value,
                      started_at: localDate,
                  }
                : { started_at: values.startedAt }),
        };
        await saveSettings(payload);
        emit('close');
    } catch {
        return;
    }
});
async function disable(): Promise<void> {
    if (isSaving.value || !state.value?.settings) return;
    try {
        const settings = state.value.settings;
        await saveSettings({
            account_id: settings.account_id,
            started_at: settings.started_at,
            opening_balance: settings.opening_balance,
            enabled: false,
        });
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
            <AppText v-if="startingNow"
                >Indica el dinero que tienes libre ahora, sin incluir lo que ya apartaste. Empezarás
                con ese importe; los movimientos ya registrados quedan fuera.</AppText
            >
            <AppText v-else
                >Conserva tu inicio actual al editar o reactivar. Para comenzar con el dinero que
                tienes hoy, elige «Establecer saldo actual».</AppText
            >
            <AppButton
                v-if="state?.settings && !startingNow"
                variant="outline"
                :disabled="isSaving"
                @click="startingNow = true"
                >Establecer saldo actual</AppButton
            >
            <AppButton
                v-if="state?.settings && startingNow"
                variant="ghost"
                :disabled="isSaving"
                @click="startingNow = false"
                >Conservar inicio actual</AppButton
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
                v-if="!startingNow && !state?.settings?.activated_at"
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
                :label="startingNow ? 'Dinero disponible ahora' : 'Saldo libre inicial'"
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
