<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import Message from 'primevue/message';
import { z } from 'zod';
import {
    AppInput,
    AppModal,
    AppPercentageSplitEditor,
    AppText,
    AppTitle,
} from '@/modules/shared/components';
import { usePrimeForm } from '@/modules/shared/composables/usePrimeForm';
import type { AppModalAction } from '@/modules/shared/types/modal';
import type {
    Account,
    AccountCustodianPayload,
    AccountCustodyTransfer,
    AccountVirtualCustodian,
} from '../types';

const props = defineProps<{
    open: boolean;
    account: Account | null;
    custodian: AccountVirtualCustodian | null;
    isSaving: boolean;
    error: string | null;
}>();
const emit = defineEmits<{
    close: [];
    save: [payload: AccountCustodianPayload | AccountCustodyTransfer[]];
}>();
const removing = computed(() => props.custodian !== null);
const draft = ref<Record<string, number>>({});
const { values, errors, setFieldValue, resetForm, handleSubmit } = usePrimeForm({
    initialValues: { name: '', openingBalance: '0.00' },
    schema: z.object({
        name: z
            .string()
            .trim()
            .max(100, 'El nombre no puede superar 100 caracteres.')
            .refine((value) => removing.value || value.length > 0, 'Ingresa un nombre.'),
        openingBalance: z
            .string()
            .regex(/^\d+(\.\d{1,2})?$/, 'Ingresa un importe válido con hasta dos decimales.')
            .refine(
                (value) =>
                    Number.isSafeInteger(Math.round(Number(value) * 100)) &&
                    Number(value) <= 999999999.99,
                'El importe es demasiado grande.',
            ),
    }),
});
const sources = computed(() => {
    if (!props.account) return [];
    const users = removing.value
        ? props.account.users
        : [...props.account.users, ...(props.account.virtualCustodians ?? [])];
    return users
        .map((user) => ({
            id: user.id,
            name: user.name,
            amount:
                props.account?.custodyByUser.find((item) => item.userId === user.id)?.amount ??
                user.custodyAmount,
        }))
        .map((user) => ({
            ...user,
            available: Math.max(
                0,
                Math.round(
                    (user.amount -
                        (props.account?.pendingReimbursements ?? [])
                            .filter(
                                (item) =>
                                    item.actionType === 'custody_to_user' &&
                                    item.fromUserId === user.id,
                            )
                            .reduce((sum, item) => sum + item.amount, 0)) *
                        100,
                ) / 100,
            ),
        }))
        .filter((user) => removing.value || user.amount > 0);
});
const totalCents = computed(() => Math.round((props.custodian?.custodyAmount ?? 0) * 100));
const allocatedCents = computed(() =>
    Object.values(draft.value).reduce((sum, amount) => sum + Math.round(amount * 100), 0),
);
const remainingCents = computed(() => totalCents.value - allocatedCents.value);
const hasPending = computed(() =>
    Boolean(
        props.custodian &&
        props.account?.pendingReimbursements.some(
            (item) =>
                item.fromUserId === props.custodian?.id || item.toUserId === props.custodian?.id,
        ),
    ),
);
const canSubmit = computed(
    () =>
        !props.isSaving &&
        (!removing.value ||
            (remainingCents.value === 0 && !hasPending.value && totalCents.value >= 0)),
);
const actions = computed<AppModalAction[]>(() => [
    { key: 'close', label: 'Cancelar', tone: 'neutral', disabled: props.isSaving },
    {
        key: 'save',
        label: removing.value ? 'Distribuir y eliminar' : 'Crear custodio',
        tone: removing.value ? 'danger' : 'primary',
        loading: props.isSaving,
        disabled: !canSubmit.value,
    },
]);
watch(
    () => [props.open, props.account?.id, props.custodian?.id],
    () => {
        draft.value = {};
        resetForm({ values: { name: '', openingBalance: '0.00' } });
    },
    { immediate: true },
);

function maximum(userId: string, amount: number): number {
    if (!removing.value) return amount;
    return Math.max(0, remainingCents.value + Math.round((draft.value[userId] ?? 0) * 100)) / 100;
}
function updateAmount(userId: string, amount: number, available: number): void {
    if (props.isSaving) return;
    draft.value = {
        ...draft.value,
        [userId]:
            Math.max(
                0,
                Math.min(Math.round(maximum(userId, available) * 100), Math.round(amount * 100)),
            ) / 100,
    };
}
function formatMoney(amount: number): string {
    return new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(amount);
}
const submit = handleSubmit((submitted) => {
    if (!canSubmit.value) return;
    const transfers = sources.value
        .map((source) => ({ userId: source.id, amount: draft.value[source.id] ?? 0 }))
        .filter((item) => item.amount > 0);
    if (removing.value) {
        emit('save', transfers);
        return;
    }
    emit('save', {
        name: submitted.name.trim(),
        openingBalance: Number(submitted.openingBalance),
        transfers,
    });
});
function action(key: string): void {
    if (key === 'save') void submit();
    if (key === 'close' && !props.isSaving) emit('close');
}
</script>

<template>
    <AppModal
        presentation="sheet"
        :open="props.open"
        :title="removing ? `Eliminar ${props.custodian?.name}` : 'Agregar custodio virtual'"
        :variant="removing ? 'danger' : 'default'"
        :actions="actions"
        @action="action"
        @close="!props.isSaving && emit('close')"
    >
        <form class="space-y-5" @submit.prevent="submit">
            <template v-if="!removing">
                <AppInput
                    id="custodian-name"
                    label="Nombre"
                    :model-value="values.name"
                    :error="errors.name"
                    :disabled="props.isSaving"
                    maxlength="100"
                    @update:model-value="setFieldValue('name', $event)"
                />
                <AppInput
                    id="custodian-opening-balance"
                    label="Saldo adicional"
                    type="number"
                    inputmode="decimal"
                    min="0"
                    step="0.01"
                    :model-value="values.openingBalance"
                    :error="errors.openingBalance"
                    :disabled="props.isSaving"
                    @update:model-value="setFieldValue('openingBalance', $event)"
                />
                <AppText size="sm"
                    >Opcional. Solo este importe se suma al balance de la cuenta.</AppText
                >
            </template>
            <div class="space-y-2">
                <AppTitle as="h3" size="sm">{{
                    removing ? 'Distribuir toda la custodia' : 'Mover fondos a este custodio'
                }}</AppTitle>
                <AppText size="sm">{{
                    removing
                        ? 'Reparte el 100% entre los usuarios. Todo el dinero permanece en la cuenta.'
                        : 'El dinero transferido cambia de custodio sin aumentar el balance.'
                }}</AppText>
            </div>
            <Message v-if="hasPending" severity="warn"
                >Resuelve los reembolsos pendientes de este custodio antes de eliminarlo.</Message
            >
            <div v-for="source in sources" :key="source.id" class="space-y-3">
                <AppText>{{ removing ? 'Para' : 'Desde' }} {{ source.name }}</AppText>
                <AppText v-if="!removing && source.available < source.amount" size="sm"
                    >Disponible para mover: {{ formatMoney(source.available) }}. El resto está
                    reservado para reembolsos.</AppText
                >
                <AppPercentageSplitEditor
                    :users="[]"
                    :model-value="{}"
                    :disabled="props.isSaving"
                    :monetary="{
                        total: removing ? totalCents / 100 : source.amount,
                        amount: draft[source.id] ?? 0,
                        maximum: maximum(source.id, source.available),
                        source: removing ? 'Por repartir' : source.name,
                        sourceRemaining: removing ? remainingCents / 100 : undefined,
                        recipient: removing ? source.name : values.name || 'Nuevo custodio',
                    }"
                    @update:amount="updateAmount(source.id, $event, source.available)"
                />
            </div>
            <div class="space-y-2 border-t border-(--app-color-border) pt-4" aria-live="polite">
                <AppText v-if="removing"
                    >Por repartir: <strong>{{ formatMoney(remainingCents / 100) }}</strong></AppText
                >
                <AppText v-else
                    >Custodia inicial:
                    <strong>{{
                        formatMoney(allocatedCents / 100 + (Number(values.openingBalance) || 0))
                    }}</strong></AppText
                >
                <AppText size="sm"
                    >Balance: {{ formatMoney(props.account?.balance ?? 0) }} →
                    {{
                        formatMoney(
                            (props.account?.balance ?? 0) +
                                (removing ? 0 : Number(values.openingBalance) || 0),
                        )
                    }}</AppText
                >
            </div>
            <Message v-if="props.error" severity="error">{{ props.error }}</Message>
        </form>
    </AppModal>
</template>
