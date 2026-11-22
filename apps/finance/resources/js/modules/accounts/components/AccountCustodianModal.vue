<script setup lang="ts">
import {
    money,
    parseMoney,
    addMoney,
    subtractMoney,
    sumMoney,
    minMoney,
    maxMoney,
    compareMoney,
    formatMoney as formatExactMoney,
} from '@/modules/shared/lib/money';
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
    renaming?: boolean;
    account: Account | null;
    custodian: AccountVirtualCustodian | null;
    isSaving: boolean;
    error: string | null;
}>();
const emit = defineEmits<{
    close: [];
    save: [payload: AccountCustodianPayload | AccountCustodyTransfer[] | string];
}>();
const removing = computed(() => props.custodian !== null && !props.renaming);
const draft = ref<Record<string, string>>({});
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
                (value) => parseMoney(value) !== null && compareMoney(value, '999999999.99') <= 0,
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
            available: maxMoney(
                '0.00',
                subtractMoney(
                    user.amount,
                    sumMoney(
                        (props.account?.pendingReimbursements ?? [])
                            .filter(
                                (item) =>
                                    item.actionType === 'custody_to_user' &&
                                    item.fromUserId === user.id,
                            )
                            .map((item) => item.amount),
                    ),
                ),
            ),
        }))
        .filter((user) => removing.value || compareMoney(user.amount, '0') > 0);
});
const totalAmount = computed(() => props.custodian?.custodyAmount ?? '0.00');
const allocatedAmount = computed(() => sumMoney(Object.values(draft.value)));
const remainingAmount = computed(() => subtractMoney(totalAmount.value, allocatedAmount.value));
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
            (compareMoney(remainingAmount.value, '0') === 0 &&
                !hasPending.value &&
                compareMoney(totalAmount.value, '0') >= 0)),
);
const actions = computed<AppModalAction[]>(() => [
    { key: 'close', label: 'Cancelar', tone: 'neutral', disabled: props.isSaving },
    {
        key: 'save',
        label: props.renaming
            ? 'Guardar nombre'
            : removing.value
              ? 'Distribuir y eliminar'
              : 'Crear custodio',
        tone: removing.value ? 'danger' : 'primary',
        loading: props.isSaving,
        disabled: !canSubmit.value,
    },
]);
watch(
    () => [props.open, props.account?.id, props.custodian?.id, props.renaming],
    () => {
        draft.value = {};
        resetForm({
            values: {
                name: props.renaming ? (props.custodian?.name ?? '') : '',
                openingBalance: '0.00',
            },
        });
    },
    { immediate: true },
);

function maximum(userId: string, amount: string): string {
    if (!removing.value) return amount;
    return maxMoney('0.00', addMoney(remainingAmount.value, draft.value[userId] ?? '0.00'));
}
function updateAmount(userId: string, amount: string, available: string): void {
    if (props.isSaving) return;
    draft.value = {
        ...draft.value,
        [userId]: maxMoney('0.00', minMoney(maximum(userId, available), amount)),
    };
}
function formatMoney(amount: string): string {
    return formatExactMoney(amount);
}
const submit = handleSubmit((submitted) => {
    if (!canSubmit.value) return;
    if (props.renaming) {
        emit('save', submitted.name.trim());
        return;
    }
    const transfers = sources.value
        .map((source) => ({ userId: source.id, amount: draft.value[source.id] ?? '0.00' }))
        .filter((item) => compareMoney(item.amount, '0') > 0);
    if (removing.value) {
        emit('save', transfers);
        return;
    }
    emit('save', {
        name: submitted.name.trim(),
        openingBalance: money(submitted.openingBalance),
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
        :title="
            props.renaming
                ? 'Renombrar custodio'
                : removing
                  ? `Eliminar ${props.custodian?.name}`
                  : 'Agregar custodio virtual'
        "
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
                    v-if="!props.renaming"
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
                <AppText v-if="!props.renaming" size="sm"
                    >Opcional. Solo este importe se suma al balance de la cuenta.</AppText
                >
            </template>
            <template v-if="!props.renaming">
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
                    >Resuelve los reembolsos pendientes de este custodio antes de
                    eliminarlo.</Message
                >
                <div v-for="source in sources" :key="source.id" class="space-y-3">
                    <AppText>{{ removing ? 'Para' : 'Desde' }} {{ source.name }}</AppText>
                    <AppText
                        v-if="!removing && compareMoney(source.available, source.amount) < 0"
                        size="sm"
                        >Disponible para mover: {{ formatMoney(source.available) }}. El resto está
                        reservado para reembolsos.</AppText
                    >
                    <AppPercentageSplitEditor
                        :users="[]"
                        :model-value="{}"
                        :disabled="props.isSaving"
                        :monetary="{
                            total: removing ? totalAmount : source.amount,
                            amount: draft[source.id] ?? '0.00',
                            maximum: maximum(source.id, source.available),
                            source: removing ? 'Por repartir' : source.name,
                            sourceRemaining: removing ? remainingAmount : undefined,
                            recipient: removing ? source.name : values.name || 'Nuevo custodio',
                        }"
                        @update:amount="updateAmount(source.id, $event, source.available)"
                    />
                </div>
                <div class="space-y-2 border-t border-(--app-color-border) pt-4" aria-live="polite">
                    <AppText v-if="removing"
                        >Por repartir: <strong>{{ formatMoney(remainingAmount) }}</strong></AppText
                    >
                    <AppText v-else
                        >Custodia inicial:
                        <strong>{{
                            formatMoney(
                                addMoney(
                                    allocatedAmount,
                                    parseMoney(values.openingBalance) ?? '0.00',
                                ),
                            )
                        }}</strong></AppText
                    >
                    <AppText size="sm"
                        >Balance: {{ formatMoney(props.account?.balance ?? '0.00') }} →
                        {{
                            formatMoney(
                                addMoney(
                                    props.account?.balance ?? '0.00',
                                    removing
                                        ? '0.00'
                                        : (parseMoney(values.openingBalance) ?? '0.00'),
                                ),
                            )
                        }}</AppText
                    >
                </div>
            </template>
            <Message v-if="props.error" severity="error">{{ props.error }}</Message>
        </form>
    </AppModal>
</template>
