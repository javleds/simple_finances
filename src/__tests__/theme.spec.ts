import { beforeEach, describe, expect, it } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';

import { useThemeStore } from '../stores/theme';

describe('theme store', () => {
  beforeEach(() => {
    setActivePinia(createPinia());
    window.localStorage.clear();
    document.documentElement.className = '';
    document.documentElement.style.colorScheme = 'light';
  });

  it('applies dark mode and persists the preference', () => {
    const themeStore = useThemeStore();

    themeStore.setTheme('dark');

    expect(themeStore.mode).toBe('dark');
    expect(document.documentElement.classList.contains('dark')).toBe(true);
    expect(document.documentElement.style.colorScheme).toBe('dark');
    expect(window.localStorage.getItem('theme-mode')).toBe('dark');
  });

  it('restores the saved theme on initialization', () => {
    window.localStorage.setItem('theme-mode', 'dark');
    const themeStore = useThemeStore();

    themeStore.initializeTheme();

    expect(themeStore.mode).toBe('dark');
    expect(document.documentElement.classList.contains('dark')).toBe(true);
  });
});
