<script setup lang="ts">
import { computed } from 'vue';
import { useRoute } from 'vue-router';

import { getStoredAuthToken } from '@/lib/api/apiClient';
import { AppCard, AppLink, AppText, AppTitle } from '@/modules/shared/components';

const route = useRoute();

const requestedPath = computed(() => route.fullPath);
const hasSession = computed(() => Boolean(getStoredAuthToken()));
const primaryRoute = computed(() =>
    hasSession.value ? { name: 'admin.dashboard' } : { name: 'auth.login' },
);
const primaryLabel = computed(() => (hasSession.value ? 'Ir al dashboard' : 'Ir al login'));
</script>

<template>
    <section
        class="flex min-h-dvh items-start justify-center bg-(--app-color-page) px-4 py-24 text-(--app-color-text) sm:items-center sm:px-6"
    >
        <div class="w-full max-w-lg">
            <AppCard
                class="border-0! bg-transparent! p-0! sm:rounded-(--app-radius-control) sm:border! sm:bg-(--app-color-surface)! sm:p-8!"
            >
                <div class="space-y-6 text-left sm:text-center">
                    <div class="space-y-2">
                        <AppText
                            class="text-xs font-semibold tracking-[0.18em] text-(--app-color-text-subtle) uppercase"
                        >
                            Error 404
                        </AppText>
                        <AppTitle as="h1">Página no encontrada</AppTitle>
                        <AppText>
                            La ruta que intentaste abrir no existe o ya no está disponible en esta
                            aplicación.
                        </AppText>
                    </div>

                    <div
                        class="border-l-2 pl-4 text-left sm:rounded-(--app-radius-control) sm:border sm:bg-(--app-color-surface-muted) sm:px-4 sm:py-3"
                        :style="{ borderColor: 'var(--app-color-border)' }"
                    >
                        <AppText size="sm" tone="subtle">Ruta solicitada</AppText>
                        <p class="mt-1 text-sm font-semibold break-all text-(--app-color-text)">
                            {{ requestedPath }}
                        </p>
                    </div>

                    <div class="flex flex-col gap-3 sm:flex-row sm:justify-center">
                        <AppLink :to="primaryRoute" variant="primary">
                            {{ primaryLabel }}
                        </AppLink>
                        <AppLink
                            :to="{ name: 'admin.accounts' }"
                            variant="secondary"
                            v-if="hasSession"
                        >
                            Ver cuentas
                        </AppLink>
                    </div>
                </div>
            </AppCard>
        </div>
    </section>
</template>
