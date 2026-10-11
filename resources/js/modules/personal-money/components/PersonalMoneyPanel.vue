<script setup lang="ts">
import { computed, ref } from 'vue';
import { createPersonalMoneySettingsSchema } from '../schemas';
import {
    AppButton,
    AppCard,
    AppDatePicker,
    AppEmptyState,
    AppInput,
    AppSearchSelect,
    AppText,
    AppTitle,
} from '@/modules/shared/components';
import { usePrimeForm } from '@/modules/shared/composables/usePrimeForm';
import { usePersonalMoney } from '../composables/usePersonalMoney';
import type { PersonalMoneyEntry, PersonalMoneyResolution } from '../types';

const {
    state,
    isLoading,
    loadError,
    mutationError,
    isSaving,
    page,
    resolution,
    retry,
    saveSettings,
    saveLink,
} = usePersonalMoney();
const editing = ref(false);
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
const money = (value: number): string =>
    new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(value);
const kinds: Record<PersonalMoneyEntry['kind'], string> = {
    personal_income: 'Ingreso personal',
    personal_expense: 'Gasto personal',
    contribution: 'Posible aportación',
    out_of_pocket: 'Pago de bolsillo',
    reimbursement: 'Reembolso',
};
const resolutions: Record<PersonalMoneyEntry['resolution'], string> = {
    personal: 'Mi dinero',
    external: 'Origen externo',
    pending: 'Por revisar',
    automatic: 'Movimiento personal',
};

function openSettings(): void {
    const settings = state.value?.settings;
    form.resetForm({
        values: {
            accountId: settings ? String(settings.account_id) : '',
            startedAt: settings?.started_at ?? localDate,
            openingBalance: String(settings?.opening_balance ?? 0),
        },
    });
    editing.value = true;
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
        editing.value = false;
    } catch {
        return;
    }
});
async function disable(): Promise<void> {
    if (isSaving.value || !state.value?.settings) return;
    try {
        await saveSettings({ ...state.value.settings, enabled: false });
    } catch {
        return;
    }
}
async function resolve(entry: PersonalMoneyEntry, value: PersonalMoneyResolution): Promise<void> {
    if (isSaving.value) return;
    try {
        await saveLink({
            source_type: entry.source_type,
            source_id: entry.source_id,
            resolution: value,
        });
    } catch {
        return;
    }
}
</script>

<template>
    <AppCard class="space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <AppTitle as="h2" size="sm">Mi dinero disponible</AppTitle>
            <AppButton v-if="state && !editing" variant="outline" @click="openSettings">{{
                enabled ? 'Configurar' : 'Activar seguimiento'
            }}</AppButton>
        </div>
        <AppText
            >Tu saldo libre, después de apartar dinero y pagar gastos. Esta información es
            privada.</AppText
        >
        <p v-if="isLoading" role="status">Cargando tu disponible...</p>
        <div v-if="loadError" role="alert" class="space-y-2">
            <AppText>{{ loadError }}</AppText>
            <AppButton variant="secondary" @click="retry()">Reintentar</AppButton>
        </div>
        <p v-if="mutationError" role="alert" class="text-(--app-color-danger)">
            {{ mutationError }}
        </p>
        <form v-if="editing" class="grid gap-4" @submit.prevent="submit">
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
                >Primero crea una cuenta individual para poder activar el seguimiento.</AppText
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
            <div class="flex flex-wrap gap-2">
                <AppButton type="submit" :disabled="isSaving || !accountOptions.length"
                    >Guardar</AppButton
                >
                <AppButton
                    type="button"
                    variant="ghost"
                    :disabled="isSaving"
                    @click="editing = false"
                    >Cancelar</AppButton
                >
                <AppButton
                    v-if="enabled"
                    type="button"
                    variant="danger"
                    :disabled="isSaving"
                    @click="disable"
                    >Desactivar</AppButton
                >
            </div>
        </form>
        <template v-if="enabled && state">
            <p v-if="state.account_available === false" role="alert">
                La cuenta personal dejó de estar disponible. Configura otra cuenta individual o
                desactiva el seguimiento.
            </p>
            <p
                v-if="state.available_balance !== null"
                class="text-3xl font-semibold"
                aria-label="Saldo disponible"
            >
                {{ money(state.available_balance ?? 0) }}
            </p>
            <p
                v-if="state.pending_count > 0"
                role="status"
                class="rounded-lg border border-amber-500 p-3"
            >
                Tienes {{ state.pending_count }} movimientos por revisar. Tu disponible puede estar
                incompleto.
            </p>
            <div class="flex gap-2" aria-label="Filtrar movimientos">
                <AppButton
                    variant="outline"
                    :aria-pressed="resolution === 'all'"
                    @click="resolution = 'all'"
                    >Todos</AppButton
                >
                <AppButton
                    variant="outline"
                    :aria-pressed="resolution === 'pending'"
                    @click="resolution = 'pending'"
                    >Por revisar</AppButton
                >
            </div>
            <AppEmptyState
                v-if="state.history.length === 0"
                message="No hay movimientos para este filtro."
            />
            <ul class="divide-y divide-(--app-color-border)">
                <li
                    v-for="entry in state.history"
                    :key="`${entry.source_type}:${entry.source_id}`"
                    class="space-y-2 py-4"
                >
                    <div class="flex justify-between gap-3">
                        <div class="min-w-0">
                            <p class="font-medium wrap-anywhere">{{ entry.concept }}</p>
                            <AppText
                                >{{ entry.account_name }} ·
                                {{ entry.occurred_at.slice(0, 10) }}</AppText
                            >
                            <AppText
                                >{{ kinds[entry.kind] }} ·
                                {{ resolutions[entry.resolution] }}</AppText
                            >
                        </div>
                        <div class="shrink-0 text-right">
                            <p class="font-semibold">{{ money(entry.effect) }}</p>
                            <AppText
                                v-if="
                                    entry.kind === 'reimbursement' && entry.raw_effect !== undefined
                                "
                                >{{ entry.raw_effect > 0 ? 'Recibido' : 'Pagado' }}</AppText
                            >
                            <AppText v-if="entry.effect === 0"
                                >Importe: {{ money(entry.amount) }}</AppText
                            >
                        </div>
                    </div>
                    <div v-if="entry.resolution !== 'automatic'" class="flex flex-wrap gap-2">
                        <AppButton
                            variant="outline"
                            :disabled="isSaving || entry.resolution === 'personal'"
                            @click="resolve(entry, 'personal')"
                            >Mi dinero</AppButton
                        >
                        <AppButton
                            variant="outline"
                            :disabled="isSaving || entry.resolution === 'external'"
                            @click="resolve(entry, 'external')"
                            >Origen externo</AppButton
                        >
                        <AppButton
                            variant="ghost"
                            :disabled="isSaving || entry.resolution === 'pending'"
                            @click="resolve(entry, 'pending')"
                            >Revisar después</AppButton
                        >
                    </div>
                </li>
            </ul>
            <div v-if="state.meta.last_page > 1" class="flex items-center justify-between gap-2">
                <AppButton
                    variant="outline"
                    :disabled="page <= 1 || isLoading || isSaving"
                    @click="page--"
                    >Anterior</AppButton
                >
                <AppText
                    >Página {{ state.meta.current_page }} de {{ state.meta.last_page }}</AppText
                >
                <AppButton
                    variant="outline"
                    :disabled="page >= state.meta.last_page || isLoading || isSaving"
                    @click="page++"
                    >Siguiente</AppButton
                >
            </div>
        </template>
    </AppCard>
</template>
