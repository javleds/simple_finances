import { computed, onScopeDispose, ref } from 'vue';
import { defineStore } from 'pinia';

export const THEME_MODE = {
  LIGHT: 'light',
  DARK: 'dark',
  SYSTEM: 'system',
} as const;

export type ThemeMode = (typeof THEME_MODE)[keyof typeof THEME_MODE];
const THEME_STORAGE_KEY = 'theme-mode';

function isThemeMode(value: string | null): value is ThemeMode {
  return value === THEME_MODE.LIGHT || value === THEME_MODE.DARK || value === THEME_MODE.SYSTEM;
}

function applyTheme(isDark: boolean): void {
  if (typeof document === 'undefined') return;
  document.documentElement.classList.toggle('dark', isDark);
  document.documentElement.style.colorScheme = isDark ? 'dark' : 'light';
}

export const useThemeStore = defineStore('theme', () => {
  const mode = ref<ThemeMode>(THEME_MODE.SYSTEM);
  const systemIsDark = ref(false);
  const isDarkMode = computed(() => mode.value === THEME_MODE.DARK || (mode.value === THEME_MODE.SYSTEM && systemIsDark.value));
  let systemPreference: MediaQueryList | null = null;

  function handleSystemChange(event: MediaQueryListEvent): void {
    systemIsDark.value = event.matches;
    if (mode.value === THEME_MODE.SYSTEM) applyTheme(isDarkMode.value);
  }

  function observeSystemTheme(): void {
    if (systemPreference || typeof window === 'undefined' || typeof window.matchMedia !== 'function') return;
    systemPreference = window.matchMedia('(prefers-color-scheme: dark)');
    systemIsDark.value = systemPreference.matches;
    systemPreference.addEventListener('change', handleSystemChange);
  }

  function setTheme(nextMode: ThemeMode): void {
    observeSystemTheme();
    mode.value = nextMode;
    applyTheme(isDarkMode.value);
    if (typeof window === 'undefined') return;
    window.localStorage.setItem(THEME_STORAGE_KEY, nextMode);
  }

  function initializeTheme(): void {
    observeSystemTheme();
    const storedTheme = typeof window === 'undefined' ? null : window.localStorage.getItem(THEME_STORAGE_KEY);
    mode.value = isThemeMode(storedTheme) ? storedTheme : THEME_MODE.SYSTEM;
    applyTheme(isDarkMode.value);
  }

  function toggleTheme(): void {
    setTheme(isDarkMode.value ? THEME_MODE.LIGHT : THEME_MODE.DARK);
  }

  onScopeDispose(() => systemPreference?.removeEventListener('change', handleSystemChange));

  return { isDarkMode, mode, initializeTheme, setTheme, toggleTheme };
});
