<script setup lang="ts">
import Checkbox from 'primevue/checkbox';
import Message from 'primevue/message';

import { AppLink } from '@/modules/shared/components';

const props = defineProps<{
    isAdult: boolean;
    adultError?: string;
    privacyPolicyAccepted: boolean;
    privacyPolicyError?: string;
    termsAccepted: boolean;
    termsError?: string;
}>();

const emit = defineEmits<{
    'update:isAdult': [value: boolean];
    touchAdult: [];
    touchPrivacyPolicy: [];
    touchTerms: [];
    'update:privacyPolicyAccepted': [value: boolean];
    'update:termsAccepted': [value: boolean];
}>();

function updateTermsAccepted(value: boolean): void {
    emit('update:termsAccepted', value);
    emit('touchTerms');
}

function updatePrivacyPolicyAccepted(value: boolean): void {
    emit('update:privacyPolicyAccepted', value);
    emit('touchPrivacyPolicy');
}
</script>

<template>
    <div
        class="space-y-3 rounded-(--app-radius-control) border px-4 py-4"
        :style="{ borderColor: 'var(--app-color-border)' }"
    >
        <label class="flex items-start gap-3">
            <Checkbox
                :modelValue="props.termsAccepted"
                binary
                :invalid="Boolean(props.termsError)"
                @update:modelValue="updateTermsAccepted"
            />
            <span class="text-sm text-(--app-color-text)">
                Acepto los
                <AppLink
                    href="/auth/terms-and-conditions"
                    target="_blank"
                    rel="noopener noreferrer"
                    variant="primary"
                    class="font-semibold"
                >
                    términos y condiciones (nueva pestaña)
                </AppLink>
            </span>
        </label>
        <Message v-if="props.termsError" severity="error" size="small" variant="simple">
            {{ props.termsError }}
        </Message>

        <label class="flex items-start gap-3">
            <Checkbox
                :modelValue="props.privacyPolicyAccepted"
                binary
                :invalid="Boolean(props.privacyPolicyError)"
                @update:modelValue="updatePrivacyPolicyAccepted"
            />
            <span class="text-sm text-(--app-color-text)">
                Acepto la
                <AppLink
                    href="/auth/privacy-policy"
                    target="_blank"
                    rel="noopener noreferrer"
                    variant="primary"
                    class="font-semibold"
                >
                    política de privacidad (nueva pestaña)
                </AppLink>
                y consiento expresamente el tratamiento de mis datos financieros y patrimoniales
                para las finalidades necesarias descritas.
            </span>
        </label>
        <Message v-if="props.privacyPolicyError" severity="error" size="small" variant="simple">
            {{ props.privacyPolicyError }}
        </Message>
        <label class="flex items-start gap-3">
            <Checkbox
                :modelValue="props.isAdult"
                binary
                :invalid="Boolean(props.adultError)"
                @update:modelValue="
                    emit('update:isAdult', $event);
                    emit('touchAdult');
                "
            />
            <span class="text-sm text-(--app-color-text)">Declaro que tengo al menos 18 años.</span>
        </label>
        <Message v-if="props.adultError" severity="error" size="small" variant="simple">{{
            props.adultError
        }}</Message>
    </div>
</template>
