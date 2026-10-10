<script setup lang="ts">
import PrivacyPolicyContent from '@/modules/auth/components/PrivacyPolicyContent.vue';
import { AppCard } from '@/modules/shared/components';
</script>

<template>
  <section
    class="relative flex min-h-dvh items-start justify-center bg-(--app-color-page) px-4 pt-20 pb-8 text-(--app-color-text) sm:items-center sm:px-6 sm:pt-24 sm:pb-10 lg:px-8"
  >
    <div class="w-full max-w-2xl">
      <AppCard
        :padded="false"
        class="border-0! bg-transparent! shadow-none! sm:border! sm:bg-(--app-color-surface)! sm:p-8!"
      >
        <PrivacyPolicyContent />
      </AppCard>
    </div>
  </section>
</template>
