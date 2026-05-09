import { computed, type ComputedRef } from 'vue';
import { useFieldError, useIsFieldTouched, useSetFieldTouched, useSubmitCount } from 'vee-validate';

type UseFormFieldInteractionResult = {
  error: ComputedRef<string | undefined>;
  touch: () => void;
};

export function useFormFieldInteraction(fieldName: string): UseFormFieldInteractionResult {
  const fieldError = useFieldError(fieldName);
  const isTouched = useIsFieldTouched(fieldName);
  const setFieldTouched = useSetFieldTouched(fieldName);
  const submitCount = useSubmitCount();

  const error = computed(() => {
    if (!fieldError.value) {
      return undefined;
    }

    return isTouched.value || submitCount.value > 0 ? fieldError.value : undefined;
  });

  function touch(): void {
    setFieldTouched(true);
  }

  return {
    error,
    touch,
  };
}
