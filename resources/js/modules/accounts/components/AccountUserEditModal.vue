<script setup lang="ts">
import { ref } from 'vue';
import { Form, FormField, type FormSubmitEvent } from '@primevue/forms';
import { zodResolver } from '@primevue/forms/resolvers/zod';
import { z } from 'zod';
import type { AppModalAction } from '@/modules/shared/types/modal';
import type { AccountMember } from '@/modules/accounts/types';
import { AppInput, AppModal, AppText } from '@/modules/shared/components';

const percentageForm = ref<InstanceType<typeof Form> | null>(null);

const editPercentage = defineModel<string>('editPercentage', { required: true });

const props = withDefaults(
    defineProps<{
        actions: ReadonlyArray<AppModalAction>;
        canSubmit: boolean;
        open: boolean;
        saveError?: string | null;
        selectedUser: AccountMember | null;
    }>(),
    {
        saveError: null,
    },
);

const emit = defineEmits<{
    close: [];
    save: [];
}>();
const resolver = zodResolver(
    z.object({
        percentage: z
            .string()
            .min(1, 'Ingresa un porcentaje.')
            .refine(
                (value) =>
                    Number.isFinite(Number(value)) && Number(value) >= 0 && Number(value) <= 100,
                'El porcentaje debe estar entre 0 y 100.',
            ),
    }),
);

function submitPercentage(event: FormSubmitEvent): void {
    const action = props.actions.find((item) => item.key === 'submit-edit-user');
    if (event.valid && props.canSubmit && !action?.loading && !action?.disabled) emit('save');
}
</script>

<template>
    <AppModal
        presentation="sheet"
        :open="props.open"
        :actions="props.actions"
        title="Editar porcentaje"
        variant="default"
        @action="$event === 'submit-edit-user' && percentageForm?.submit()"
        @close="emit('close')"
    >
        <Form
            :key="`${props.open}-${props.selectedUser?.id}`"
            ref="percentageForm"
            :resolver="resolver"
            class="space-y-4"
            @submit="submitPercentage"
        >
            <AppText v-if="props.selectedUser">
                Ajusta la participación de <strong>{{ props.selectedUser.name }}</strong> dentro de
                esta cuenta.
            </AppText>

            <FormField v-slot="$field" name="percentage" :initial-value="editPercentage">
                <AppInput
                    id="account-user-percentage"
                    v-model="editPercentage"
                    @update:model-value="$field.props.onChange({ value: $event })"
                    label="Porcentaje"
                    type="number"
                    inputmode="decimal"
                    min="0"
                    max="100"
                    step="0.01"
                    placeholder="0.00"
                    :error="
                        props.saveError ??
                        $field.error?.message ??
                        (editPercentage && !props.canSubmit
                            ? 'El porcentaje debe estar entre 0 y 100.'
                            : undefined)
                    "
                    required
                />
            </FormField>
        </Form>
    </AppModal>
</template>
