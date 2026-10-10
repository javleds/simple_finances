import { computed, type ComputedRef } from 'vue';

import { getPrimeFormContext } from './usePrimeForm';

type UseFormFieldInteractionResult = {
    error: ComputedRef<string | undefined>;
    touch: () => void;
};

export function useFormFieldInteraction(fieldName: string): UseFormFieldInteractionResult {
    const { errors: formErrors, submitCount, getFieldState } = getPrimeFormContext();

    const error = computed(() => {
        const fieldError = formErrors.value[fieldName];

        if (!fieldError) {
            return undefined;
        }

        return getFieldState(fieldName)?.touched || submitCount.value > 0 ? fieldError : undefined;
    });

    function touch(): void {
        const state = getFieldState(fieldName);
        if (state) {
            state.touched = true;
        }
    }

    return {
        error,
        touch,
    };
}
