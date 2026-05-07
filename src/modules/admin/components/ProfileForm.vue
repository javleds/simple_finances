<script setup lang="ts">
import { watch } from 'vue';

import { useProfileForm } from '@/modules/admin/composables/useProfileForm';
import type { Profile, ProfileWritePayload } from '@/modules/admin/schemas/profileSchemas';
import { AppInput, AppPasswordInput, AppText } from '@/modules/shared/components';

type FormState = {
  canSubmit: boolean;
  isSubmitting: boolean;
};

const props = withDefaults(
  defineProps<{
    formId?: string;
    initialValues?: Partial<Profile> | null;
    serverError?: string | null;
  }>(),
  {
    formId: 'profile-form',
    initialValues: null,
    serverError: null,
  },
);

const emit = defineEmits<{
  submit: [payload: ProfileWritePayload];
  stateChange: [payload: FormState];
}>();

const {
  name,
  email,
  phoneNumber,
  password,
  passwordConfirmation,
  errors,
  isSubmitting,
  isSubmitDisabled,
  meta,
  submitForm,
} = useProfileForm({
  initialValues: () => props.initialValues,
});

watch(
  [isSubmitDisabled, isSubmitting, meta],
  () => {
    emit('stateChange', {
      canSubmit: !isSubmitDisabled.value,
      isSubmitting: isSubmitting.value,
    });
  },
  { immediate: true, deep: true },
);

async function handleSubmit(): Promise<void> {
  const payload = await submitForm();

  if (!payload) {
    return;
  }

  emit('submit', payload);
}
</script>

<template>
  <form :id="props.formId" class="space-y-6" @submit.prevent="handleSubmit">
    <section v-if="props.serverError" class="rounded-xl border border-(--app-color-danger) px-4 py-3">
      <AppText size="sm" class="text-(--app-color-danger)!">
        {{ props.serverError }}
      </AppText>
    </section>

    <section class="space-y-5">
      <AppInput
        id="profile-name"
        v-model="name"
        label="Nombre"
        placeholder="Tu nombre completo"
        :error="errors.name"
        required
      />

      <AppInput
        id="profile-email"
        v-model="email"
        label="Correo"
        type="email"
        placeholder="tu@correo.com"
        :error="errors.email"
        required
      />

      <AppInput
        id="profile-phone-number"
        v-model="phoneNumber"
        label="Teléfono"
        placeholder="55 1234 5678"
        :error="errors.phoneNumber"
      />
    </section>

    <section
      class="space-y-4 rounded-xl border bg-(--app-color-surface-muted) px-4 py-4"
      :style="{ borderColor: 'var(--app-color-border)' }"
    >
      <div class="space-y-1">
        <p class="text-sm font-semibold text-(--app-color-text)">Seguridad</p>
        <AppText size="sm">
          Deja la contraseña vacía si no quieres actualizarla en este momento.
        </AppText>
      </div>

      <AppPasswordInput
        id="profile-password"
        v-model="password"
        label="Contraseña"
        placeholder="Nueva contraseña"
        autocomplete="new-password"
        :error="errors.password"
      />

      <AppPasswordInput
        id="profile-password-confirmation"
        v-model="passwordConfirmation"
        label="Confirmar contraseña"
        placeholder="Confirma la nueva contraseña"
        autocomplete="new-password"
        :error="errors.passwordConfirmation"
      />
    </section>
  </form>
</template>
