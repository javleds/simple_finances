import { zodResolver } from '@primevue/forms/resolvers/zod';
import { useForm as usePrimeVueForm, type useFormFieldState } from '@primevue/forms/useform';
import {
    computed,
    getCurrentInstance,
    nextTick,
    ref,
    watch,
    type ComputedRef,
    type Ref,
} from 'vue';
import type { ZodType } from 'zod';

type FormContext = {
    errors: ComputedRef<Record<string, string | undefined>>;
    submitCount: Ref<number>;
    getFieldState: (name: string) => useFormFieldState | undefined;
};

const formContexts = new WeakMap<object, FormContext>();

export function getPrimeFormContext(): FormContext {
    const instance = getCurrentInstance();
    const context = instance ? formContexts.get(instance) : undefined;

    if (!context) {
        throw new Error('Form field interaction requires usePrimeForm in the same component.');
    }

    return context;
}

export function usePrimeForm<T extends Record<string, unknown>>(options: {
    schema: ZodType<T>;
    initialValues: T;
}) {
    const formOptions = {
        initialValues: { ...options.initialValues },
        resolver: zodResolver(options.schema),
        validateOnValueUpdate: false,
        validateOnMount: false,
    };
    const form = usePrimeVueForm<T>(formOptions);
    const submitCount = ref(0);
    const isSubmitting = ref(false);
    const isValidated = ref(false);
    const values = {} as T;

    for (const key of Object.keys(options.initialValues)) {
        form.defineField(key);
        Object.defineProperty(values, key, {
            enumerable: true,
            get: () => form.getFieldState(key)?.value,
        });
    }

    const errors = computed<Record<string, string | undefined>>(() =>
        Object.fromEntries(
            Object.keys(values).map((key) => {
                const error: unknown = form.getFieldState(key)?.error;
                const message =
                    typeof error === 'object' && error !== null && 'message' in error
                        ? String(error.message)
                        : undefined;
                return [key, message];
            }),
        ),
    );
    const meta = computed(() => ({
        valid:
            isValidated.value && Object.keys(values).every((key) => form.getFieldState(key)?.valid),
    }));

    async function validate(): Promise<void> {
        await form.validate('');
        isValidated.value = true;
    }

    watch(
        () => Object.values(values),
        () => void validate(),
        { deep: true },
    );
    void validate();

    const instance = getCurrentInstance();
    if (instance) {
        formContexts.set(instance, { errors, submitCount, getFieldState: form.getFieldState });
    }

    function setFieldValue<K extends keyof T>(key: K, value: T[K], shouldValidate = true): void {
        form.setFieldValue(String(key), value);
        if (shouldValidate) {
            void validate();
        }
    }

    function resetForm(next: { values: T }): void {
        formOptions.initialValues = { ...next.values };
        form.reset();
        submitCount.value = 0;
        isValidated.value = false;
        void nextTick(validate);
    }

    function handleSubmit<TResult>(callback: (submittedValues: T) => TResult) {
        return async (): Promise<Awaited<TResult> | undefined> => {
            if (isSubmitting.value) {
                return undefined;
            }

            isSubmitting.value = true;
            submitCount.value += 1;

            try {
                return await form.handleSubmit((result: { valid: boolean; values: T }) => {
                    isValidated.value = true;
                    if (!result.valid) {
                        return undefined;
                    }

                    return callback(result.values);
                })();
            } finally {
                isSubmitting.value = false;
            }
        };
    }

    return { errors, handleSubmit, isSubmitting, meta, resetForm, setFieldValue, values };
}
