import { createInertiaApp } from '@inertiajs/vue3';
import createServer from '@inertiajs/vue3/server';
import { renderToString } from '@vue/server-renderer';
import { createSSRApp, h, DefineComponent } from 'vue';
import { ZiggyVue } from 'ziggy-js/vue';

const appName = import.meta.env.VITE_APP_NAME ?? 'GRM Sierra Leone';

createServer((page) =>
    createInertiaApp({
        page,
        render: renderToString,
        title: (title) => (title ? `${title} · ${appName}` : appName),
        resolve: (name) => {
            const pages = import.meta.glob<DefineComponent>('./Pages/**/*.vue', { eager: true });
            return pages[`./Pages/${name}.vue`];
        },
        setup({ App, props, plugin }) {
            return createSSRApp({ render: () => h(App, props) })
                .use(plugin)
                .use(ZiggyVue);
        },
    }),
);
