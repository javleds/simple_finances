<script setup lang="ts">
import Message from 'primevue/message';
import { ref } from 'vue';

import ProfileForm from '@/modules/admin/components/ProfileForm.vue';
import { useProfile } from '@/modules/admin/composables/useProfile';
import type { ProfileWritePayload } from '@/modules/admin/schemas/profileSchemas';
import { AppButton, AppCard, AppText, AppTitle } from '@/modules/shared/components';

type FormState = {
    canSubmit: boolean;
    isSubmitting: boolean;
};

const formState = ref<FormState>({ canSubmit: false, isSubmitting: false });
const {
    profile,
    hasProfile,
    isLoading,
    isSaving,
    loadError,
    saveError,
    loadProfile,
    updateProfile,
} = useProfile();

async function handleSubmit(payload: ProfileWritePayload): Promise<void> {
    if (isSaving.value) return;
    await updateProfile(payload);
}

function handleFormStateChange(state: FormState): void {
    formState.value = state;
}
</script>

<template>
    <section class="space-y-6 lg:max-w-3xl">
        <header class="space-y-1">
            <AppTitle as="h1" size="md">Perfil</AppTitle>
            <AppText>
                Administra tus datos personales y actualiza tu contraseña desde una sola pantalla.
            </AppText>
        </header>

        <Message v-if="loadError && hasProfile" severity="error">{{ loadError }}</Message>

        <section
            v-if="isLoading && !hasProfile"
            class="rounded-(--app-radius-control) border px-4 py-10 text-center"
        >
            <AppText>Cargando perfil...</AppText>
        </section>

        <section
            v-else-if="loadError && !hasProfile"
            class="space-y-3 rounded-(--app-radius-control) border px-4 py-6 text-center"
        >
            <AppText>{{ loadError }}</AppText>
            <div class="flex justify-center">
                <AppButton variant="secondary" @click="loadProfile">Reintentar</AppButton>
            </div>
        </section>

        <section v-else-if="profile" class="space-y-4">
            <AppCard>
                <ProfileForm
                    :initial-values="profile"
                    :server-error="saveError"
                    @state-change="handleFormStateChange"
                    @submit="handleSubmit"
                />
            </AppCard>

            <div class="flex justify-end">
                <AppButton
                    class="w-full sm:w-auto"
                    form="profile-form"
                    type="submit"
                    variant="primary"
                    :disabled="!formState.canSubmit || isSaving"
                    :loading="isSaving"
                >
                    {{ isSaving ? 'Guardando...' : 'Guardar perfil' }}
                </AppButton>
            </div>
        </section>
    </section>
</template>
