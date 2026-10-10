<script setup lang="ts">
import Checkbox from 'primevue/checkbox';
import Message from 'primevue/message';

import { AppLink } from '@/modules/shared/components';

const props = defineProps<{
    privacyPolicyAccepted: boolean;
    privacyPolicyError?: string;
    termsAccepted: boolean;
    termsError?: string;
}>();

const emit = defineEmits<{
    openPrivacy: [];
    openTerms: [];
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
        class="space-y-3 rounded-xl border px-4 py-4"
        :style="{ borderColor: 'var(--app-color-border)' }"
    >
        <label class="flex items-start gap-3">
            <Checkbox
                :model-value="props.termsAccepted"
                binary
                :invalid="Boolean(props.termsError)"
                @update:model-value="updateTermsAccepted"
            />
            <span class="text-sm text-(--app-color-text)">
                Acepto los
                <AppLink
                    href=""
                    variant="primary"
                    class="font-semibold"
                    @click.prevent="emit('openTerms')"
                >
                    términos y condiciones
                </AppLink>
            </span>
        </label>
        <Message v-if="props.termsError" severity="error" size="small" variant="simple">
            {{ props.termsError }}
        </Message>

        <label class="flex items-start gap-3">
            <Checkbox
                :model-value="props.privacyPolicyAccepted"
                binary
                :invalid="Boolean(props.privacyPolicyError)"
                @update:model-value="updatePrivacyPolicyAccepted"
            />
            <span class="text-sm text-(--app-color-text)">
                Acepto la
                <AppLink
                    href=""
                    variant="primary"
                    class="font-semibold"
                    @click.prevent="emit('openPrivacy')"
                >
                    política de privacidad
                </AppLink>
            </span>
        </label>
        <Message v-if="props.privacyPolicyError" severity="error" size="small" variant="simple">
            {{ props.privacyPolicyError }}
        </Message>
    </div>
</template>
