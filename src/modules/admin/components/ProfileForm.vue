<script setup lang="ts">
import { computed, reactive } from 'vue';

import { AppInput, AppPasswordInput, AppText } from '@/modules/shared/components';

type ProfileFormSubmit = {
  name: string;
  password: string | null;
  passwordConfirmation: string | null;
};

const props = withDefaults(
  defineProps<{
    formId?: string;
    initialName?: string;
  }>(),
  {
    formId: 'profile-form',
    initialName: '',
  },
);

const emit = defineEmits<{
  submit: [payload: ProfileFormSubmit];
}>();

const state = reactive({
  name: props.initialName,
  password: '',
  passwordConfirmation: '',
});

const requiresPasswordConfirmation = computed(() => state.password.trim().length > 0);

function submitForm(): void {
  emit('submit', {
    name: state.name.trim(),
    password: requiresPasswordConfirmation.value ? state.password : null,
    passwordConfirmation: requiresPasswordConfirmation.value ? state.passwordConfirmation : null,
  });
}
</script>

<template>
  <form :id="props.formId" class="space-y-6" @submit.prevent="submitForm">
    <section class="space-y-5">
      <AppInput
        id="profile-name"
        v-model="state.name"
        label="Nombre"
        placeholder="Tu nombre completo"
        required
      />
    </section>

    <section
      class="space-y-4 rounded-xl border bg-[var(--app-color-surface-muted)] px-4 py-4"
      :style="{ borderColor: 'var(--app-color-border)' }"
    >
      <div class="space-y-1">
        <p class="text-sm font-semibold text-[var(--app-color-text)]">Seguridad</p>
        <AppText size="sm">
          Deja la contraseña vacía si no quieres actualizarla en este momento.
        </AppText>
      </div>

      <AppPasswordInput
        id="profile-password"
        v-model="state.password"
        label="Contraseña"
        placeholder="Nueva contraseña"
        autocomplete="new-password"
      />

      <AppPasswordInput
        id="profile-password-confirmation"
        v-model="state.passwordConfirmation"
        label="Confirmar contraseña"
        placeholder="Confirma la nueva contraseña"
        autocomplete="new-password"
        :required="requiresPasswordConfirmation"
      />
    </section>
  </form>
</template>
