import { createApp } from 'vue';
import { createPinia } from 'pinia';
import { VueQueryPlugin } from '@tanstack/vue-query';
import PrimeVue from 'primevue/config';
import { appPreset, spanishLocale } from './lib/primevue';
import './main.css';
import '@primeui/chart-style/style.css';
import '@primeui/chart-style/themes/primeone.css';

import App from './App.vue';
import router from './router';
import { useThemeStore } from './stores/theme';
import { queryClient } from './modules/shared/lib/queryClient';

const app = createApp(App);
const pinia = createPinia();

app.use(pinia);
app.use(PrimeVue, {
    license: import.meta.env.VITE_PRIMEVUE_LICENSE_KEY,
    locale: spanishLocale,
    theme: {
        preset: appPreset,
        options: {
            darkModeSelector: '.dark',
            cssLayer: {
                name: 'primevue',
                order: 'theme, base, primevue, components, utilities',
            },
        },
    },
});
app.use(VueQueryPlugin, { queryClient });
app.use(router);

const themeStore = useThemeStore(pinia);
themeStore.initializeTheme();

app.mount('#app');
