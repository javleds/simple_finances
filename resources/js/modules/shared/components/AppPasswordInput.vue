<script setup lang="ts">
import Password from 'primevue/password';
import Message from 'primevue/message';

defineOptions({
    inheritAttrs: false,
});

const props = defineProps<{
    id: string;
    label: string;
    modelValue?: string | number | null;
    error?: string;
}>();

const emit = defineEmits<{
    'update:modelValue': [value: string];
    blur: [event: FocusEvent];
}>();

function handleBlur(event: FocusEvent): void {
    emit('blur', event);
}
</script>

<template>
    <div class="space-y-2.5">
        <div class="flex min-h-5 items-center justify-between gap-4">
            <label
                :for="props.id"
                class="text-sm font-medium"
                :class="props.error ? 'text-(--app-color-danger)' : 'text-(--app-color-label)'"
            >
                {{ props.label }}
            </label>
        </div>

        <Password
            :input-id="props.id"
            :model-value="String(props.modelValue ?? '')"
            :invalid="Boolean(props.error)"
            :feedback="false"
            toggle-mask
            fluid
            input-class="min-h-12 w-full"
            :input-props="{
                ...$attrs,
                'aria-describedby': props.error ? `${props.id}-error` : undefined,
            }"
            @update:model-value="emit('update:modelValue', $event ?? '')"
            @blur="handleBlur"
        />
        <Message
            v-if="props.error"
            :id="`${props.id}-error`"
            severity="error"
            variant="simple"
            size="small"
        >
            {{ props.error }}
        </Message>
    </div>
</template>
