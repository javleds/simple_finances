<script setup lang="ts">
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

function readChecked(event: Event): boolean {
  return (event.target as HTMLInputElement).checked;
}

function updateTermsAccepted(event: Event): void {
  emit('update:termsAccepted', readChecked(event));
  emit('touchTerms');
}

function updatePrivacyPolicyAccepted(event: Event): void {
  emit('update:privacyPolicyAccepted', readChecked(event));
  emit('touchPrivacyPolicy');
}
</script>

<template>
  <div class="space-y-3 rounded-xl border px-4 py-4" :style="{ borderColor: 'var(--app-color-border)' }">
    <label class="flex items-start gap-3">
      <input
        :checked="props.termsAccepted"
        type="checkbox"
        class="mt-1 h-4 w-4 rounded border-(--app-color-input-border) text-(--app-color-primary) focus:ring-(--app-color-focus-ring)"
        @change="updateTermsAccepted"
      />
      <span class="text-sm text-(--app-color-text)">
        Acepto los
        <AppLink href="" variant="primary" class="font-semibold" @click.prevent="emit('openTerms')">
          términos y condiciones
        </AppLink>
      </span>
    </label>
    <p v-if="props.termsError" class="text-sm text-(--app-color-danger)">
      {{ props.termsError }}
    </p>

    <label class="flex items-start gap-3">
      <input
        :checked="props.privacyPolicyAccepted"
        type="checkbox"
        class="mt-1 h-4 w-4 rounded border-(--app-color-input-border) text-(--app-color-primary) focus:ring-(--app-color-focus-ring)"
        @change="updatePrivacyPolicyAccepted"
      />
      <span class="text-sm text-(--app-color-text)">
        Acepto la
        <AppLink href="" variant="primary" class="font-semibold" @click.prevent="emit('openPrivacy')">
          política de privacidad
        </AppLink>
      </span>
    </label>
    <p v-if="props.privacyPolicyError" class="text-sm text-(--app-color-danger)">
      {{ props.privacyPolicyError }}
    </p>
  </div>
</template>
