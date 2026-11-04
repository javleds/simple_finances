<script setup lang="ts">
import { CheckIcon, ComputerDesktopIcon, MoonIcon, SunIcon } from '@heroicons/vue/24/outline';
import Menu from 'primevue/menu';
import { computed, ref, useId, type Component } from 'vue';
import { THEME_MODE, useThemeStore, type ThemeMode } from '@/stores/theme';
import AppIconButton from './AppIconButton.vue';

const themeStore = useThemeStore();
const menuRef = ref<InstanceType<typeof Menu> | null>(null);
const isOpen = ref(false);
const menuId = useId();
const options: readonly { mode: ThemeMode; label: string; icon: Component }[] = [
  { mode: THEME_MODE.LIGHT, label: 'Claro', icon: SunIcon },
  { mode: THEME_MODE.DARK, label: 'Oscuro', icon: MoonIcon },
  { mode: THEME_MODE.SYSTEM, label: 'Sistema', icon: ComputerDesktopIcon },
];
const selectedOption = computed(() => options.find(option => option.mode === themeStore.mode)!);
const menuItems = computed(() => options.map(option => ({
  ...option,
  command: () => themeStore.setTheme(option.mode),
})));
</script>

<template>
  <div class="relative shrink-0">
    <AppIconButton :ariaLabel="`Elegir tema: ${selectedOption.label}`" aria-haspopup="menu" :aria-controls="menuId" :aria-expanded="isOpen" class="h-11! w-11!" @click="menuRef?.toggle($event)">
      <component :is="selectedOption.icon" class="h-5 w-5" aria-hidden="true" />
    </AppIconButton>
    <Menu :id="menuId" ref="menuRef" :model="menuItems" popup aria-label="Tema visual" @show="isOpen = true" @hide="isOpen = false">
      <template #item="{ item, props: itemProps }">
        <a v-bind="itemProps.action" role="menuitemradio" :aria-checked="item.mode === themeStore.mode" class="flex min-h-11 items-center gap-3">
          <component :is="item.icon" class="h-5 w-5 shrink-0" aria-hidden="true" />
          <span class="flex-1">{{ item.label }}</span>
          <CheckIcon v-if="item.mode === themeStore.mode" class="h-4 w-4 text-(--app-color-primary)" aria-hidden="true" />
        </a>
      </template>
    </Menu>
  </div>
</template>
