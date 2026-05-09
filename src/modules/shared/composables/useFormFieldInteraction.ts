import { computed, ref, type ComputedRef } from 'vue';
import { useFormErrors, useSubmitCount } from 'vee-validate';

type UseFormFieldInteractionResult = {
  error: ComputedRef<string | undefined>;
  touch: () => void;
};

export function useFormFieldInteraction(fieldName: string): UseFormFieldInteractionResult {
  const formErrors = useFormErrors<Record<string, string>>();
  const submitCount = useSubmitCount();
  const isTouched = ref(false);

  const error = computed(() => {
    const fieldError = formErrors.value[fieldName];

    if (!fieldError) {
      return undefined;
    }

    return isTouched.value || submitCount.value > 0 ? fieldError : undefined;
  });

  function touch(): void {
    isTouched.value = true;
  }

  return {
    error,
    touch,
  };
}
