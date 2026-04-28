<script setup lang="ts">
import { BuildingLibraryIcon, PlusIcon } from '@heroicons/vue/24/outline';

import AccountBalanceCard from '@/modules/accounts/components/AccountBalanceCard.vue';
import { AppButton, AppCard, AppText, AppTitle } from '@/modules/shared/components';

const accounts = [
  {
    name: 'Cuenta concentradora',
    type: 'Operación principal',
    balance: '$184,220',
    status: 'Activa',
  },
  {
    name: 'Reserva tributaria',
    type: 'Resguardo fiscal',
    balance: '$62,080',
    status: 'Alineada',
  },
  {
    name: 'Pagos y nómina',
    type: 'Dispersión semanal',
    balance: '$44,697',
    status: 'Programada',
  },
];
</script>

<template>
  <div class="space-y-5">
    <AppCard class="overflow-hidden !p-0">
      <div class="relative px-5 py-6 sm:px-6">
        <div
          class="absolute inset-x-0 top-0 h-24 bg-[linear-gradient(135deg,var(--app-color-primary),color-mix(in_srgb,var(--app-color-primary)_48%,white))] opacity-95"
        />

        <div class="relative space-y-4">
          <div class="flex items-start justify-between gap-4">
            <div class="space-y-1">
              <AppText size="sm" tone="subtle" class="!text-[var(--app-color-primary-foreground)]/80">
                Cuentas conectadas
              </AppText>
              <AppTitle as="h2" size="sm" class="!text-[var(--app-color-primary-foreground)]">
                Gestión de cuentas
              </AppTitle>
            </div>

            <div
              class="rounded-2xl border border-white/20 bg-white/10 px-3 py-2 text-right backdrop-blur"
            >
              <p class="text-xs font-medium text-[var(--app-color-primary-foreground)]/75">
                Total
              </p>
              <p class="text-sm font-semibold text-[var(--app-color-primary-foreground)]">3 cuentas</p>
            </div>
          </div>

          <div
            class="rounded-3xl border border-white/15 bg-[var(--app-color-surface)]/96 p-4 shadow-[0_18px_45px_-30px_rgba(15,23,42,0.45)]"
          >
            <div class="flex items-center justify-between gap-4">
              <div>
                <AppText size="sm" tone="subtle">Saldo agregado</AppText>
                <p class="mt-2 text-3xl font-semibold tracking-tight text-[var(--app-color-text)]">
                  $290,997
                </p>
              </div>

              <div
                class="flex h-12 w-12 items-center justify-center rounded-2xl bg-[color-mix(in_srgb,var(--app-color-primary)_12%,transparent)] text-[var(--app-color-primary)]"
              >
                <BuildingLibraryIcon class="h-6 w-6" />
              </div>
            </div>
          </div>
        </div>
      </div>
    </AppCard>

    <div class="flex items-center justify-between gap-3">
      <div class="space-y-1">
        <AppTitle as="h2" size="sm">Resumen</AppTitle>
        <AppText>Consulta el estado operativo de cada cuenta de la facility.</AppText>
      </div>

      <AppButton variant="outline">
        <PlusIcon class="mr-2 h-4 w-4" />
        Agregar
      </AppButton>
    </div>

    <section class="space-y-4">
      <AccountBalanceCard
        v-for="account in accounts"
        :key="account.name"
        :name="account.name"
        :type="account.type"
        :balance="account.balance"
        :status="account.status"
      />
    </section>
  </div>
</template>
