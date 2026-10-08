<script setup lang="ts">
import PrivacyPolicyContent from '@/modules/auth/components/PrivacyPolicyContent.vue';
import TermsAndConditionsContent from '@/modules/auth/components/TermsAndConditionsContent.vue';
import { AppModal } from '@/modules/shared/components';

export type RegisterLegalDocument = 'terms' | 'privacy' | null;

const props = defineProps<{
  activeDocument: RegisterLegalDocument;
}>();

const emit = defineEmits<{
  close: [];
}>();
</script>

<template>
  <AppModal
    :open="props.activeDocument === 'terms'"
    title="Términos y condiciones"
    close-label="Cerrar"
    @close="emit('close')"
  >
    <TermsAndConditionsContent />
  </AppModal>

  <AppModal
    :open="props.activeDocument === 'privacy'"
    title="Política de privacidad"
    close-label="Cerrar"
    @close="emit('close')"
  >
    <PrivacyPolicyContent />
  </AppModal>
</template>
