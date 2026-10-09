import { createApp } from 'vue';
import { createPinia } from 'pinia';
import { VueQueryPlugin } from '@tanstack/vue-query';
import PrimeVue from 'primevue/config';
import Aura from '@primeuix/themes/aura';
import './main.css';
import '@vueform/multiselect/themes/default.css';
import '@vuepic/vue-datepicker/dist/main.css';

import App from './App.vue';
import router from './router';
import { useThemeStore } from './stores/theme';
import { queryClient } from './modules/shared/lib/queryClient';

const app = createApp(App);
const pinia = createPinia();

app.use(pinia);
app.use(PrimeVue, {
    license: import.meta.env.VITE_PRIMEVUE_LICENSE_KEY,
    theme: {
        preset: Aura,
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
