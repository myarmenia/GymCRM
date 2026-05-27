import '../css/app.css';
import './bootstrap';

// ============ 1. СНАЧАЛА JQUERY ============
import $ from 'jquery';
window.$ = window.jQuery = $;

// ============ 2. ПОТОМ BOOTSTRAP И ЗАВИСИМОСТИ ============
import 'select2/dist/css/select2.css';
import '../assets/vendor/fonts/iconify-icons.css';
import '../assets/vendor/libs/node-waves/node-waves.css';
import '../assets/vendor/libs/pickr/pickr-themes.css';
import '../assets/vendor/css/core.css';
import '../assets/css/style.css';

// ============ 3. ПОТОМ JS-ПЛАГИНЫ ============
import '../assets/vendor/js/bootstrap.js';
import '../assets/vendor/js/menu.js';

// ============ 4. SELECT2 (после jquery) ============
import select2 from 'select2';
select2($);

// ============ 5. Inertia и Vue ============
import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createApp, h } from 'vue';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';
import Toast from 'vue-toastification';
import 'vue-toastification/dist/index.css';

// ============ 6. Ваши модули ============
import { initHelper } from './modules/helper';
import { initConfig } from './modules/config';
import { initMain } from './modules/main';

// ============ 7. ГЛОБАЛЬНАЯ ФУНКЦИЯ для реинициализации ============
window.initTemplatePlugins = () => {

    // Переинициализируем ваши модули
    if (typeof initHelper === 'function') initHelper();
    if (typeof initConfig === 'function') initConfig();
    if (typeof initMain === 'function') initMain();

    // Переинициализируем бутстраповские компоненты
    if (typeof bootstrap !== 'undefined') {
        // Инициализируем tooltips
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    }
};


const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob('./Pages/**/*.vue'),
        ),
    setup({ el, App, props, plugin }) {
        const app = createApp({ render: () => h(App, props) });

        app.use(plugin);
        app.use(ZiggyVue);
        app.use(Toast, {
            timeout: 3000,
            position: 'top-right',
        });

        // Глобально регистрируем компоненты
        app.component('GlobalConfirm', () => import('@/Components/GlobalConfirm.vue'));

        return app.mount(el);
    },
    progress: {
        color: '#4B5563',
    },
});

// ============ 8. AXIOS ============
import axios from 'axios';
window.axios = axios;
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
