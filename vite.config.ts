import tailwindcss from '@tailwindcss/vite';
import react from '@vitejs/plugin-react';
import { fileURLToPath } from 'node:url';
import { defineConfig } from 'vitest/config';

// The interface ships compiled in dist/ and is served by the package itself
// (route "meridian.assets"), so asset URLs are relative to the entry file.
export default defineConfig({
    plugins: [react(), tailwindcss()],
    base: './',
    publicDir: false,
    resolve: {
        alias: { '@': fileURLToPath(new URL('./resources/js', import.meta.url)) },
    },
    build: {
        outDir: 'dist',
        emptyOutDir: true,
        manifest: true,
        sourcemap: false,
        chunkSizeWarningLimit: 700,
        rollupOptions: {
            input: 'resources/js/app.tsx',
        },
    },
    test: {
        environment: 'jsdom',
        include: ['resources/js/**/*.test.{ts,tsx}'],
    },
});
