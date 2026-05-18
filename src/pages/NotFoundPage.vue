<script setup lang="ts">
import { computed } from 'vue';
import { useRoute } from 'vue-router';

import { getStoredAuthToken } from '@/lib/api/apiClient';
import { AppCard, AppLink, AppText, AppTitle } from '@/modules/shared/components';

const route = useRoute();

const requestedPath = computed(() => route.fullPath);
const hasSession = computed(() => Boolean(getStoredAuthToken()));
const primaryRoute = computed(() => (hasSession.value ? { name: 'admin.dashboard' } : { name: 'auth.login' }));
const primaryLabel = computed(() => (hasSession.value ? 'Ir al dashboard' : 'Ir al login'));
</script>

<template>
  <section
    class="flex min-h-screen items-center justify-center bg-(--app-color-page) px-4 py-10 text-(--app-color-text) sm:px-6"
  >
    <div class="w-full max-w-lg">
      <AppCard class="rounded-3xl">
        <div class="space-y-6 text-center">
          <div class="space-y-2">
            <AppText
              class="text-xs font-semibold tracking-[0.18em] text-(--app-color-text-subtle) uppercase"
            >
              Error 404
            </AppText>
            <AppTitle as="h1">Página no encontrada</AppTitle>
            <AppText>
              La ruta que intentaste abrir no existe o ya no está disponible en esta aplicación.
            </AppText>
          </div>

          <div
            class="rounded-2xl border bg-(--app-color-surface-muted) px-4 py-3 text-left"
            :style="{ borderColor: 'var(--app-color-border)' }"
          >
            <AppText size="sm" tone="subtle">Ruta solicitada</AppText>
            <p class="mt-1 break-all text-sm font-semibold text-(--app-color-text)">
              {{ requestedPath }}
            </p>
          </div>

          <div class="flex flex-col gap-3 sm:flex-row sm:justify-center">
            <AppLink :to="primaryRoute" variant="primary">
              {{ primaryLabel }}
            </AppLink>
            <AppLink :to="{ name: 'admin.accounts' }" variant="secondary" v-if="hasSession">
              Ver cuentas
            </AppLink>
          </div>
        </div>
      </AppCard>
    </div>
  </section>
</template>
