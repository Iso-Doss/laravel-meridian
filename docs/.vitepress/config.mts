import { defineConfig } from 'vitepress';

export default defineConfig({
    title: 'Meridian',
    description: 'Horizon for Laravel database queues: dashboard, metrics and auto-scaling supervisors, without Redis.',
    base: '/laravel-meridian/',
    cleanUrls: true,
    lastUpdated: true,
    head: [['link', { rel: 'icon', type: 'image/svg+xml', href: '/laravel-meridian/icon.svg' }]],
    themeConfig: {
        logo: { light: '/logo-light.svg', dark: '/logo-dark.svg', alt: 'Meridian' },
        siteTitle: false,
        nav: [
            { text: 'Guide', link: '/guide/introduction' },
            { text: 'Changelog', link: 'https://github.com/Iso-Doss/laravel-meridian/blob/main/CHANGELOG.md' },
            { text: 'Packagist', link: 'https://packagist.org/packages/iso-doss/laravel-meridian' },
        ],
        sidebar: [
            {
                text: 'Getting started',
                items: [
                    { text: 'Introduction', link: '/guide/introduction' },
                    { text: 'Installation', link: '/guide/installation' },
                    { text: 'Configuration', link: '/guide/configuration' },
                    { text: 'Running Meridian', link: '/guide/running' },
                ],
            },
            {
                text: 'Using Meridian',
                items: [
                    { text: 'The dashboard', link: '/guide/dashboard' },
                    { text: 'Tags', link: '/guide/tags' },
                    { text: 'Errors', link: '/guide/errors' },
                    { text: 'Job progress', link: '/guide/progress' },
                    { text: 'Scheduled tasks', link: '/guide/scheduled-tasks' },
                    { text: 'Notifications and alerts', link: '/guide/notifications' },
                    { text: 'Metrics', link: '/guide/metrics' },
                    { text: 'Pulse, Filament and terminal', link: '/guide/integrations' },
                ],
            },
            {
                text: 'Reference',
                items: [
                    { text: 'How it works', link: '/guide/how-it-works' },
                    { text: 'Upgrading', link: '/guide/upgrading' },
                    { text: 'Contributing', link: '/guide/contributing' },
                ],
            },
        ],
        socialLinks: [{ icon: 'github', link: 'https://github.com/Iso-Doss/laravel-meridian' }],
        editLink: {
            pattern: 'https://github.com/Iso-Doss/laravel-meridian/edit/main/docs/:path',
            text: 'Edit this page on GitHub',
        },
        search: { provider: 'local' },
        outline: [2, 3],
        footer: {
            message: 'Released under the MIT License.',
            copyright: 'Copyright © DOSSOU Israël (Iso-Doss)',
        },
    },
});
