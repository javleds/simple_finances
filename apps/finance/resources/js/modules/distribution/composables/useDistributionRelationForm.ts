import { usePrimeForm } from '@/modules/shared/composables/usePrimeForm';
import { computed, toValue, watch } from 'vue';

import {
    createDefaultDistributionRelationFormValues,
    distributionRelationFormSchema,
    mapDistributionRelationFormToWritePayload,
} from '../schemas/distributionSchemas';
import type {
    DistributionRelation,
    DistributionRelationFormValues,
    DistributionRelationWritePayload,
} from '../types';

type UseDistributionRelationFormOptions = {
    fixedIncomeId: string;
    initialValues?:
        | Partial<DistributionRelation>
        | null
        | (() => Partial<DistributionRelation> | null | undefined);
};

export function useDistributionRelationForm(options: UseDistributionRelationFormOptions) {
    const resolvedInitialValues = computed(() =>
        createDefaultDistributionRelationFormValues(toValue(options.initialValues)),
    );

    const { errors, handleSubmit, isSubmitting, meta, resetForm, setFieldValue, values } =
        usePrimeForm<DistributionRelationFormValues>({
            schema: distributionRelationFormSchema,
            initialValues: resolvedInitialValues.value,
        });

    watch(
        resolvedInitialValues,
        (nextValues) => {
            resetForm({
                values: nextValues,
            });
        },
        { deep: true },
    );

    const name = computed({
        get: () => values.name,
        set: (value: string) => setFieldValue('name', value, true),
    });

    const amount = computed({
        get: () => values.amount,
        set: (value: string) => setFieldValue('amount', value, true),
    });

    const type = computed({
        get: () => values.type,
        set: (value: DistributionRelationFormValues['type']) => setFieldValue('type', value, true),
    });

    const isSubmitDisabled = computed(() => isSubmitting.value || !meta.value.valid);

    const submitForm = handleSubmit(
        (submittedValues): DistributionRelationWritePayload =>
            mapDistributionRelationFormToWritePayload(options.fixedIncomeId, submittedValues),
    );

    return {
        name,
        amount,
        type,
        errors,
        meta,
        isSubmitting,
        isSubmitDisabled,
        submitForm,
    };
}
