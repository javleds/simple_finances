<script setup lang="ts">
import TermsAndConditionsContent from '@/modules/auth/components/TermsAndConditionsContent.vue';
import { AppCard, AppLink } from '@/modules/shared/components';
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
                <TermsAndConditionsContent />
                <div class="mt-6 border-t pt-4">
                    <AppLink :to="{ name: 'auth.register' }">Volver al registro</AppLink>
                </div>
            </AppCard>
        </div>
    </section>
</template>
