<script setup lang="ts">
import { computed, ref, watch, type Component } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { ArrowPathIcon, ClockIcon, WalletIcon } from '@heroicons/vue/24/outline';
import {
    AppActionMenu,
    AppButton,
    AppCard,
    AppContextTabs,
    AppEmptyState,
    AppHeroMetric,
    AppLink,
    AppListState,
    AppModal,
    AppSectionHeader,
    AppText,
    AppTitle,
} from '@/modules/shared/components';
import PersonalMoneySettingsModal from '../components/PersonalMoneySettingsModal.vue';
import { usePersonalMoney } from '../composables/usePersonalMoney';
import type { PersonalMoneyEntry, PersonalMoneyResolution } from '../types';

const { state, isLoading, loadError, mutationError, isSaving, page, resolution, retry, saveLink } =
    usePersonalMoney();
const route = useRoute();
const router = useRouter();
const editing = ref(false);
const helpOpen = ref(false);
const enabled = computed(() => state.value?.settings?.enabled === true);
const accountName = computed(
    () =>
        state.value?.accounts.find((account) => account.id === state.value?.settings?.account_id)
            ?.name,
);
watch(
    () => route.query.resolution,
    (value) => {
        resolution.value = value === 'pending' ? 'pending' : 'all';
    },
    { immediate: true },
);
function selectResolution(value: string): void {
    void router.replace({
        query: { ...route.query, resolution: value === 'pending' ? 'pending' : undefined },
    });
}
const money = (value: number): string =>
    new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(value);
const kinds: Record<PersonalMoneyEntry['kind'], string> = {
    personal_income: 'Ingreso personal',
    personal_expense: 'Gasto personal',
    contribution: 'Posible aportación',
    out_of_pocket: 'Pago de bolsillo',
    reimbursement: 'Reembolso',
};
const resolutions: Record<PersonalMoneyEntry['resolution'], string> = {
    personal: 'Mi dinero',
    external: 'Origen externo',
    pending: 'Por revisar',
    automatic: 'Movimiento personal',
};
function entryActions(
    entry: PersonalMoneyEntry,
): Array<{ key: string; label: string; icon: Component }> {
    return [
        { key: 'personal', label: 'Usar mi dinero', icon: WalletIcon },
        { key: 'external', label: 'Usar origen externo', icon: ArrowPathIcon },
        { key: 'pending', label: 'Volver a revisar', icon: ClockIcon },
    ].filter((action) => action.key !== entry.resolution);
}
async function resolve(entry: PersonalMoneyEntry, value: string): Promise<void> {
    if (isSaving.value || !['personal', 'external', 'pending'].includes(value)) return;
    try {
        await saveLink({
            source_type: entry.source_type,
            source_id: entry.source_id,
            resolution: value as PersonalMoneyResolution,
        });
    } catch {
        return;
    }
}
</script>

<template>
    <div class="space-y-6">
        <header class="space-y-3">
            <AppLink :to="{ name: 'admin.dashboard' }" variant="subtle">Volver a Inicio</AppLink>
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="space-y-1">
                    <AppTitle as="h1" size="md">Mi dinero disponible</AppTitle>
                    <AppText>Tu saldo libre, con tus gastos personales y compartidos.</AppText>
                </div>
                <AppButton v-if="state" variant="outline" @click="editing = true">{{
                    enabled ? 'Configurar' : 'Configurar mi disponible'
                }}</AppButton>
            </div>
        </header>
        <AppListState
            :is-loading="isLoading"
            :has-items="!!state"
            loading-label="Cargando tu disponible..."
            :error="loadError"
            @retry="retry()"
        >
            <template v-if="state">
                <div v-if="loadError" role="alert" class="flex flex-wrap items-center gap-2">
                    <AppText>{{ loadError }}</AppText>
                    <AppButton variant="ghost" @click="retry()">Reintentar</AppButton>
                </div>
                <AppSectionHeader
                    title="Tu disponible"
                    description="Estimación privada · Solo visible para ti"
                >
                    <template #actions
                        ><AppButton variant="ghost" @click="helpOpen = true"
                            >Cómo se calcula</AppButton
                        ></template
                    >
                    <template #primary-metric>
                        <AppHeroMetric
                            v-if="enabled && state.available_balance !== null"
                            label="Saldo libre estimado"
                            :value="money(state.available_balance)"
                            aria-label="Saldo disponible"
                        />
                        <AppText v-else-if="enabled" role="alert"
                            >La cuenta personal dejó de estar disponible. Configura otra cuenta
                            individual o desactiva este cálculo.</AppText
                        >
                        <AppText v-else
                            >Elige tu cuenta personal y un saldo inicial para empezar. Tú decides
                            qué movimientos compartidos afectan tu disponible.</AppText
                        >
                        <AppText v-if="enabled && accountName" size="sm" class="mt-3"
                            >{{ accountName }} · Desde {{ state.settings?.started_at }}</AppText
                        >
                    </template>
                </AppSectionHeader>
                <AppCard v-if="enabled" class="space-y-4">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <AppTitle as="h2" size="sm">Movimientos</AppTitle>
                        <AppText size="sm"
                            >{{ state.meta.total }}
                            {{ resolution === 'pending' ? 'por revisar' : 'en total' }}</AppText
                        >
                    </div>
                    <AppText
                        v-if="state.pending_count > 0"
                        role="status"
                        class="rounded-(--app-radius-control) bg-(--app-color-surface-muted) p-3"
                        >Tienes {{ state.pending_count }} movimientos por revisar. Tu disponible
                        puede estar incompleto.</AppText
                    >
                    <AppContextTabs
                        :model-value="resolution"
                        :options="[
                            { value: 'all', label: 'Todos' },
                            { value: 'pending', label: 'Por revisar' },
                        ]"
                        @update:model-value="selectResolution"
                    />
                    <p v-if="mutationError" role="alert" class="text-(--app-color-danger)">
                        {{ mutationError }}
                    </p>
                    <AppEmptyState
                        v-if="state.history.length === 0"
                        message="No hay movimientos para este filtro."
                    />
                    <ul class="divide-y divide-(--app-color-border)">
                        <li
                            v-for="entry in state.history"
                            :key="`${entry.source_type}:${entry.source_id}`"
                            class="space-y-2 py-4"
                        >
                            <div class="flex justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="font-medium wrap-anywhere">{{ entry.concept }}</p>
                                    <AppText size="sm"
                                        >{{ entry.account_name }} ·
                                        {{ entry.occurred_at.slice(0, 10) }}</AppText
                                    >
                                    <AppText size="sm"
                                        >{{ kinds[entry.kind] }} ·
                                        {{ resolutions[entry.resolution] }}</AppText
                                    >
                                </div>
                                <div class="flex shrink-0 items-start gap-1">
                                    <div class="text-right">
                                        <p class="font-semibold tabular-nums">
                                            {{ money(entry.effect) }}
                                        </p>
                                        <AppText
                                            v-if="
                                                entry.kind === 'reimbursement' &&
                                                entry.raw_effect !== undefined
                                            "
                                            >{{
                                                entry.raw_effect > 0 ? 'Recibido' : 'Pagado'
                                            }}</AppText
                                        >
                                        <AppText v-if="entry.effect === 0"
                                            >Importe: {{ money(entry.amount) }}</AppText
                                        >
                                    </div>
                                    <AppActionMenu
                                        v-if="
                                            entry.resolution !== 'automatic' &&
                                            entry.resolution !== 'pending'
                                        "
                                        :actions="entryActions(entry)"
                                        @action="resolve(entry, $event)"
                                    />
                                </div>
                            </div>
                            <div v-if="entry.resolution === 'pending'" class="flex flex-wrap gap-2">
                                <AppButton
                                    variant="outline"
                                    :disabled="isSaving"
                                    @click="resolve(entry, 'personal')"
                                    >Mi dinero</AppButton
                                >
                                <AppButton
                                    variant="outline"
                                    :disabled="isSaving"
                                    @click="resolve(entry, 'external')"
                                    >Origen externo</AppButton
                                >
                            </div>
                        </li>
                    </ul>
                    <div
                        v-if="state.meta.last_page > 1"
                        class="flex items-center justify-between gap-2"
                    >
                        <AppButton
                            variant="outline"
                            :disabled="page <= 1 || isLoading || isSaving"
                            @click="page--"
                            >Anterior</AppButton
                        >
                        <AppText
                            >Página {{ state.meta.current_page }} de
                            {{ state.meta.last_page }}</AppText
                        >
                        <AppButton
                            variant="outline"
                            :disabled="page >= state.meta.last_page || isLoading || isSaving"
                            @click="page++"
                            >Siguiente</AppButton
                        >
                    </div>
                </AppCard>
            </template>
        </AppListState>
        <PersonalMoneySettingsModal :open="editing" @close="editing = false" />
        <AppModal :open="helpOpen" title="Cómo se calcula tu disponible" @close="helpOpen = false">
            <div class="space-y-4">
                <AppText
                    >Es una estimación de tu saldo libre a partir de los movimientos que
                    registras.</AppText
                >
                <AppText
                    >Parte del saldo libre al inicio de la fecha elegida, antes de sus movimientos y
                    excluyendo el dinero que ya apartaste. Incluye los ingresos y gastos de tu
                    cuenta personal desde esa fecha.</AppText
                >
                <AppText
                    >Tú eliges qué aportaciones, pagos de bolsillo y reembolsos afectan el cálculo.
                    Los movimientos con origen externo no modifican tu disponible; los que están por
                    revisar quedan pendientes de tu decisión.</AppText
                >
                <AppText
                    >Solo tú puedes consultar tu disponible y elegir estos vínculos. Los demás
                    miembros pueden seguir usando las cuentas compartidas como siempre.</AppText
                >
            </div>
        </AppModal>
    </div>
</template>
