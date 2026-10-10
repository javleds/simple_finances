<script setup lang="ts">
import TermsAndConditionsContent from '@/modules/auth/components/TermsAndConditionsContent.vue';
import { AppCard } from '@/modules/shared/components';
</script>

<template>
  <section
    class="relative flex min-h-screen items-center justify-center overflow-hidden bg-(--app-color-page) px-4 pt-24 pb-10 text-(--app-color-text) sm:px-6 lg:px-8"
  >
    <div
      class="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_top,_var(--app-color-page-glow),_transparent_48%)]"
    />

    <div class="w-full max-w-2xl">
      <AppCard>
        <TermsAndConditionsContent />
      </AppCard>
    </div>
  </section>
</template>
