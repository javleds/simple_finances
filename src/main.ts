import { createApp } from 'vue';
import { createPinia } from 'pinia';
import './main.css';

import App from './App.vue';
import router from './router';
import { useThemeStore } from './stores/theme';

const app = createApp(App);
const pinia = createPinia();

app.use(pinia);
app.use(router);

const themeStore = useThemeStore(pinia);
themeStore.initializeTheme();

app.mount('#app');
