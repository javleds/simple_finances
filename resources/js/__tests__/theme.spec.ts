import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { createPinia, disposePinia, getActivePinia, setActivePinia } from 'pinia';

import { THEME_MODE, useThemeStore } from '../stores/theme';

function mockSystemTheme(initialIsDark: boolean = false) {
    const preference = new EventTarget();
    let isDark = initialIsDark;
    Object.defineProperty(preference, 'matches', { get: () => isDark });
    const addListener = vi.spyOn(preference, 'addEventListener');
    const removeListener = vi.spyOn(preference, 'removeEventListener');
    const matchMedia = vi.fn(() => preference as MediaQueryList);
    vi.stubGlobal('matchMedia', matchMedia);

    function changeSystemTheme(nextIsDark: boolean): void {
        isDark = nextIsDark;
        const event = new Event('change');
        Object.defineProperty(event, 'matches', { value: nextIsDark });
        preference.dispatchEvent(event);
    }

    return { changeSystemTheme, addListener, removeListener, matchMedia };
}

describe('theme store', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        mockSystemTheme();
        window.localStorage.clear();
        document.documentElement.className = '';
        document.documentElement.style.colorScheme = 'light';
    });

    afterEach(() => {
        const pinia = getActivePinia();
        if (pinia) disposePinia(pinia);
        vi.unstubAllGlobals();
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

    it.each([false, true])('uses the device preference by default (dark: %s)', (isDark) => {
        mockSystemTheme(isDark);
        const themeStore = useThemeStore();

        themeStore.initializeTheme();

        expect(themeStore.mode).toBe(THEME_MODE.SYSTEM);
        expect(themeStore.isDarkMode).toBe(isDark);
        expect(document.documentElement.classList.contains('dark')).toBe(isDark);
        expect(document.documentElement.style.colorScheme).toBe(isDark ? 'dark' : 'light');
    });

    it('restores the system preference and follows device changes', () => {
        const device = mockSystemTheme(true);
        window.localStorage.setItem('theme-mode', THEME_MODE.SYSTEM);
        const themeStore = useThemeStore();

        themeStore.initializeTheme();
        expect(themeStore.mode).toBe(THEME_MODE.SYSTEM);
        expect(document.documentElement.classList.contains('dark')).toBe(true);

        device.changeSystemTheme(false);
        expect(themeStore.isDarkMode).toBe(false);
        expect(document.documentElement.classList.contains('dark')).toBe(false);
        expect(document.documentElement.style.colorScheme).toBe('light');

        device.changeSystemTheme(true);
        expect(themeStore.isDarkMode).toBe(true);
        expect(document.documentElement.classList.contains('dark')).toBe(true);
        expect(window.localStorage.getItem('theme-mode')).toBe(THEME_MODE.SYSTEM);
    });

    it.each([THEME_MODE.LIGHT, THEME_MODE.DARK])(
        'keeps explicit %s mode through device changes and resumes system mode',
        (mode) => {
            const isDark = mode === THEME_MODE.DARK;
            const device = mockSystemTheme(!isDark);
            const themeStore = useThemeStore();
            themeStore.initializeTheme();

            themeStore.setTheme(mode);
            expect(themeStore.isDarkMode).toBe(isDark);
            expect(document.documentElement.classList.contains('dark')).toBe(isDark);

            device.changeSystemTheme(isDark);
            device.changeSystemTheme(!isDark);
            expect(themeStore.mode).toBe(mode);
            expect(document.documentElement.classList.contains('dark')).toBe(isDark);
            expect(document.documentElement.style.colorScheme).toBe(mode);
            expect(window.localStorage.getItem('theme-mode')).toBe(mode);

            themeStore.setTheme(THEME_MODE.SYSTEM);
            expect(themeStore.isDarkMode).toBe(!isDark);
            expect(document.documentElement.classList.contains('dark')).toBe(!isDark);
            expect(window.localStorage.getItem('theme-mode')).toBe(THEME_MODE.SYSTEM);

            device.changeSystemTheme(isDark);
            expect(document.documentElement.classList.contains('dark')).toBe(isDark);
        },
    );

    it('subscribes once across initialization and removes the listener on disposal', () => {
        const device = mockSystemTheme();
        const themeStore = useThemeStore();

        themeStore.initializeTheme();
        themeStore.initializeTheme();
        themeStore.setTheme(THEME_MODE.SYSTEM);
        expect(device.matchMedia).toHaveBeenCalledTimes(1);
        expect(device.addListener).toHaveBeenCalledTimes(1);

        themeStore.$dispose();
        expect(device.removeListener).toHaveBeenCalledWith('change', expect.any(Function));
        device.changeSystemTheme(true);
        expect(document.documentElement.classList.contains('dark')).toBe(false);
    });
});
