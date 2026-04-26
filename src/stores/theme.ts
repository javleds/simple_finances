import { computed, ref } from 'vue';
import { defineStore } from 'pinia';

export type ThemeMode = 'light' | 'dark';

const THEME_STORAGE_KEY = 'theme-mode';

function isThemeMode(value: string | null): value is ThemeMode {
  return value === 'light' || value === 'dark';
}

function getSystemTheme(): ThemeMode {
  if (typeof window === 'undefined') {
    return 'light';
  }

  return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
}

function applyTheme(mode: ThemeMode): void {
  if (typeof document === 'undefined') {
    return;
  }

  document.documentElement.classList.toggle('dark', mode === 'dark');
  document.documentElement.style.colorScheme = mode;
}

export const useThemeStore = defineStore('theme', () => {
  const mode = ref<ThemeMode>('light');
  const isDarkMode = computed(() => mode.value === 'dark');

  function setTheme(nextMode: ThemeMode): void {
    mode.value = nextMode;
    applyTheme(nextMode);

    if (typeof window === 'undefined') {
      return;
    }

    window.localStorage.setItem(THEME_STORAGE_KEY, nextMode);
  }

  function initializeTheme(): void {
    if (typeof window === 'undefined') {
      applyTheme(mode.value);
      return;
    }

    const storedTheme = window.localStorage.getItem(THEME_STORAGE_KEY);
    const initialTheme = isThemeMode(storedTheme) ? storedTheme : getSystemTheme();

    mode.value = initialTheme;
    applyTheme(initialTheme);
  }

  function toggleTheme(): void {
    setTheme(isDarkMode.value ? 'light' : 'dark');
  }

  return {
    isDarkMode,
    mode,
    initializeTheme,
    setTheme,
    toggleTheme,
  };
});
