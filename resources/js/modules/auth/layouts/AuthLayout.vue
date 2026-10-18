<script setup lang="ts">
import { AppThemeMenu } from '@/modules/shared/components';
</script>

<template>
    <div class="relative min-h-screen bg-(--app-color-page)">
        <div class="absolute inset-x-0 top-4 z-10 px-4 sm:px-6 lg:px-8">
            <div class="mx-auto flex max-w-md items-center justify-between">
                <img
                    src="/brand/logo.png"
                    alt="Finanzas Simples"
                    width="56"
                    height="56"
                    class="h-14 w-14 object-contain"
                />
                <AppThemeMenu />
            </div>
        </div>

        <RouterView />
    </div>
</template>
