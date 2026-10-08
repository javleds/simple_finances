import { beforeEach, describe, expect, it } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';

import { THEME_MODE, useThemeStore } from '../stores/theme';

describe('theme store', () => {
  beforeEach(() => {
    setActivePinia(createPinia());
    window.localStorage.clear();
    document.documentElement.className = '';
    document.documentElement.style.colorScheme = 'light';
  });

  it('applies dark mode and persists the preference', () => {
    const themeStore = useThemeStore();

    themeStore.setTheme(THEME_MODE.DARK);

    expect(themeStore.mode).toBe(THEME_MODE.DARK);
    expect(document.documentElement.classList.contains('dark')).toBe(true);
    expect(document.documentElement.style.colorScheme).toBe(THEME_MODE.DARK);
    expect(window.localStorage.getItem('theme-mode')).toBe(THEME_MODE.DARK);
  });

  it('restores the saved theme on initialization', () => {
    window.localStorage.setItem('theme-mode', THEME_MODE.DARK);
    const themeStore = useThemeStore();

    themeStore.initializeTheme();

    expect(themeStore.mode).toBe(THEME_MODE.DARK);
    expect(document.documentElement.classList.contains('dark')).toBe(true);
  });
});
