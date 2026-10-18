<script setup lang="ts">
import PrivacyPolicyContent from '@/modules/auth/components/PrivacyPolicyContent.vue';
import TermsAndConditionsContent from '@/modules/auth/components/TermsAndConditionsContent.vue';
import { AppButton, AppCard, AppLink } from '@/modules/shared/components';

export type RegisterLegalDocument = 'terms' | 'privacy';

const props = defineProps<{
    document: RegisterLegalDocument;
}>();

const emit = defineEmits<{
    close: [];
}>();

function returnToForm(): void {
    const fields = window.document.getElementById('register-legal-fields');
    const link = window.document.getElementById(`register-${props.document}-link`);
    link?.focus({ preventScroll: true });
    fields?.scrollIntoView({
        behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches
            ? 'instant'
            : 'smooth',
        block: 'start',
    });
}
</script>

<template>
    <section
        id="register-legal-document"
        tabindex="-1"
        aria-label="Documento legal"
        class="scroll-mt-20 focus:outline-none"
    >
        <AppCard class="space-y-4">
            <TermsAndConditionsContent v-if="props.document === 'terms'" embedded />
            <PrivacyPolicyContent v-else embedded />
            <AppButton type="button" full-width @click="returnToForm">
                Volver al formulario
            </AppButton>
            <div class="flex flex-wrap items-center justify-between gap-3 border-t pt-4">
                <AppLink
                    :to="{ name: props.document === 'terms' ? 'auth.terms' : 'auth.privacy' }"
                    target="_blank"
                    rel="noopener noreferrer"
                >
                    Abrir página completa (nueva pestaña)
                </AppLink>
                <AppButton type="button" variant="secondary" @click="emit('close')">
                    Cerrar documento
                </AppButton>
            </div>
        </AppCard>
    </section>
</template>
