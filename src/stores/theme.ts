import { computed, ref } from 'vue';
import { defineStore } from 'pinia';

export const THEME_MODE = {
  LIGHT: 'light',
  DARK: 'dark',
} as const;

export type ThemeMode = (typeof THEME_MODE)[keyof typeof THEME_MODE];

const THEME_STORAGE_KEY = 'theme-mode';

function isThemeMode(value: string | null): value is ThemeMode {
  return value === THEME_MODE.LIGHT || value === THEME_MODE.DARK;
}

function getSystemTheme(): ThemeMode {
  if (typeof window === 'undefined') {
    return THEME_MODE.LIGHT;
  }

  return window.matchMedia('(prefers-color-scheme: dark)').matches
    ? THEME_MODE.DARK
    : THEME_MODE.LIGHT;
}

function applyTheme(mode: ThemeMode): void {
  if (typeof document === 'undefined') {
    return;
  }

  document.documentElement.classList.toggle('dark', mode === THEME_MODE.DARK);
  document.documentElement.style.colorScheme = mode;
}

export const useThemeStore = defineStore('theme', () => {
  const mode = ref<ThemeMode>(THEME_MODE.LIGHT);
  const isDarkMode = computed(() => mode.value === THEME_MODE.DARK);

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
    setTheme(isDarkMode.value ? THEME_MODE.LIGHT : THEME_MODE.DARK);
  }

  return {
    isDarkMode,
    mode,
    initializeTheme,
    setTheme,
    toggleTheme,
  };
});
