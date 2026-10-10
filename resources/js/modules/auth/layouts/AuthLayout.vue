<script setup lang="ts">
import { AppThemeMenu } from '@/modules/shared/components';
</script>

<template>
    <div class="relative min-h-screen bg-(--app-color-page)">
        <div class="absolute inset-x-0 top-4 z-10 px-4 sm:px-6 lg:px-8">
            <div class="mx-auto flex max-w-md justify-end">
                <AppThemeMenu />
            </div>
        </div>

        <RouterView />
    </div>
</template>
