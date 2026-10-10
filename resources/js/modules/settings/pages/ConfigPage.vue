<script setup lang="ts">
import Message from 'primevue/message';
import { useNotificationSettings } from '@/modules/settings/composables/useNotificationSettings';
import WhatsappConnectionCard from '@/modules/settings/components/WhatsappConnectionCard.vue';
import { CalculatorIcon } from '@heroicons/vue/24/outline';
import { RouterLink } from 'vue-router';
import { AppCard, AppSwitch, AppText, AppTitle } from '@/modules/shared/components';

const {
    globalNotificationSettings,
    accountNotificationSettings,
    isLoading,
    isSaving,
    saveError,
    toggleGlobalSetting,
    toggleAccountSetting,
} = useNotificationSettings();
</script>

<template>
    <div class="space-y-5">
        <WhatsappConnectionCard />
        <section class="space-y-3">
            <div class="space-y-1">
                <AppTitle as="h2" size="sm">Configuración de notificaciones</AppTitle>
                <AppText
                    >Elige los avisos que quieres recibir por correo. Los cambios se guardan
                    automáticamente.</AppText
                >
            </div>

            <Message v-if="saveError" severity="error">{{ saveError }}</Message>
            <AppText v-if="isSaving" role="status" size="sm">Guardando preferencias...</AppText>

            <div v-if="isLoading" class="rounded-2xl border px-4 py-6 text-center">
                <AppText>Cargando configuración...</AppText>
            </div>

            <div v-else class="space-y-4">
                <AppCard
                    v-for="setting in globalNotificationSettings"
                    :key="setting.id"
                    class="rounded-2xl p-4! shadow-none"
                    :class="
                        setting.enabled
                            ? 'border-(--app-color-primary)! bg-(--app-color-primary)/5!'
                            : ''
                    "
                >
                    <div class="flex items-center justify-between gap-4">
                        <div class="min-w-0 space-y-1">
                            <p class="text-sm font-semibold text-(--app-color-text)">
                                {{ setting.title }}
                            </p>
                            <AppText size="sm">{{ setting.description }}</AppText>
                        </div>

                        <div class="flex shrink-0 flex-col items-center gap-1.5">
                            <AppSwitch
                                :disabled="isSaving"
                                :model-value="setting.enabled"
                                :aria-label="`Alternar ${setting.title}`"
                                @update:model-value="void toggleGlobalSetting(setting.id)"
                            />
                            <span
                                class="text-xs font-medium"
                                :class="
                                    setting.enabled
                                        ? 'text-(--app-color-primary)'
                                        : 'text-(--app-color-text-subtle)'
                                "
                                >{{ setting.enabled ? 'Activada' : 'Desactivada' }}</span
                            >
                        </div>
                    </div>
                </AppCard>
            </div>
        </section>

        <section class="space-y-3" aria-labelledby="utilities-title">
            <div class="space-y-1">
                <AppTitle id="utilities-title" as="h2" size="sm">Utilidades</AppTitle>
                <AppText>Herramientas rápidas para tomar decisiones sin guardar datos.</AppText>
            </div>

            <RouterLink
                :to="{ name: 'admin.settings.utilities.credit-card-payoff' }"
                class="block rounded-3xl border transition hover:border-(--app-color-border-strong) focus:ring-4 focus:ring-(--app-color-focus-ring) focus:outline-none"
                :style="{ borderColor: 'var(--app-color-border)' }"
            >
                <AppCard :padded="false" class="rounded-3xl! p-4!">
                    <div class="flex items-center gap-4">
                        <div
                            class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-(--app-color-primary)/10 text-(--app-color-primary)"
                        >
                            <CalculatorIcon class="h-6 w-6" />
                        </div>
                        <div class="min-w-0 flex-1 space-y-1">
                            <p class="text-sm font-semibold text-(--app-color-text)">
                                Pago de tarjetas
                            </p>
                            <AppText size="sm"
                                >Compara tu deuda con lo que ya has ahorrado.</AppText
                            >
                        </div>
                        <span aria-hidden="true" class="text-xl text-(--app-color-text-subtle)"
                            >›</span
                        >
                    </div>
                </AppCard>
            </RouterLink>
        </section>

        <section class="space-y-3">
            <div class="space-y-1">
                <AppTitle as="h2" size="sm">Notificación por cuentas</AppTitle>
                <AppText
                    >Activa o apaga avisos individuales según la cuenta que quieras seguir.</AppText
                >
            </div>

            <div v-if="!isLoading" class="space-y-4">
                <AppCard
                    v-for="setting in accountNotificationSettings"
                    :key="setting.id"
                    class="rounded-2xl p-4! shadow-none"
                    :class="
                        setting.enabled
                            ? 'border-(--app-color-primary)! bg-(--app-color-primary)/5!'
                            : ''
                    "
                >
                    <div class="flex items-center justify-between gap-4">
                        <div class="min-w-0">
                            <p
                                class="[display:-webkit-box] overflow-hidden text-sm leading-5 font-semibold text-(--app-color-text) [-webkit-box-orient:vertical] [-webkit-line-clamp:2]"
                            >
                                {{ setting.accountName }}
                            </p>
                        </div>

                        <div class="flex shrink-0 flex-col items-center gap-1.5">
                            <AppSwitch
                                :disabled="isSaving"
                                :model-value="setting.enabled"
                                :aria-label="`Alternar notificaciones de ${setting.accountName}`"
                                @update:model-value="void toggleAccountSetting(setting.id)"
                            />
                            <span
                                class="text-xs font-medium"
                                :class="
                                    setting.enabled
                                        ? 'text-(--app-color-primary)'
                                        : 'text-(--app-color-text-subtle)'
                                "
                                >{{ setting.enabled ? 'Activada' : 'Desactivada' }}</span
                            >
                        </div>
                    </div>
                </AppCard>
            </div>
        </section>
    </div>
</template>
