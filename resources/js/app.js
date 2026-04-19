import '../css/app.css';

import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';

// Ziggy exposes `Ziggy` globally via the @routes Blade directive. We pull
// `route()` off the package and plant it on window so the <script setup>
// pages can call `route('name')` without importing it each file.
import { ZiggyVue } from 'ziggy-js';

const appName = 'GRM Sierra Leone';

createInertiaApp({
    title: (title) => (title ? `${title} · ${appName}` : appName),
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob('./Pages/**/*.vue'),
        ),
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue)
            .mount(el);
    },
    progress: { color: '#0f172a' },
});
