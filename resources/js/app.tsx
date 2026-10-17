import '../css/app.css';
import { createInertiaApp } from '@inertiajs/react';
import type { ComponentType } from 'react';
import { createRoot } from 'react-dom/client';

// Nonce of the application's Content-Security-Policy (for the styles injected by Inertia).
const nonce = document.querySelector<HTMLMetaElement>('meta[property="csp-nonce"]')?.nonce || undefined;

const pages = import.meta.glob<{ default: ComponentType }>('./pages/**/*.tsx', { eager: true });

createInertiaApp({
    id: 'meridian',
    nonce,
    title: (title) => (title ? `${title} · Meridian` : 'Meridian'),
    resolve: (name) => {
        const page = pages[`./pages/${name}.tsx`];

        if (!page) {
            throw new Error(`Unknown Meridian page: ${name}`);
        }

        return page;
    },
    setup({ el, App, props }) {
        createRoot(el!).render(<App {...props} />);
    },
    progress: { color: 'var(--primary)', delay: 250 },
});
