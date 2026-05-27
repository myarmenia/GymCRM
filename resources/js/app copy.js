import '../css/app.css';
import './bootstrap';


import '../assets/vendor/fonts/iconify-icons.css';
// import '../assets/vendor/libs/@algolia/autocomplete-js.js';
import '../assets/vendor/libs/node-waves/node-waves.css';
import '../assets/vendor/libs/pickr/pickr-themes.css';
import '../assets/vendor/libs/pickr/pickr-themes.css';
import '../assets/vendor/css/core.css';
import '../assets/css/style.css';
// import '../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css';
import 'vue-toastification/dist/index.css';

import 'select2/dist/css/select2.css';
import 'select2/dist/css/select2.min.css';
// import '../assets/vendor/libs/select2/select2.css';


// import '../assets/vendor/libs/jquery/jquery.js';
// import '../assets/vendor/libs/popper/popper.js';
import '../assets/vendor/js/bootstrap.js';
// import '../assets/vendor/libs/node-waves/node-waves.js';
// import '../assets/vendor/libs/pickr/pickr.js';
// import '../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js';
// import '../assets/vendor/libs/hammer/hammer.js';

import '../assets/vendor/js/menu.js';





import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createApp, h } from 'vue';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';
import Toast from 'vue-toastification';
import * as bootstrap from 'bootstrap';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';
window.bootstrap = bootstrap;
window.Menu = Menu;

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

        return app.mount(el);
    },
    progress: {
        color: '#4B5563',
    },
});


