import { defineConfig } from 'vite';
import laravel, { refreshPaths } from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
            ],
            refresh: [
                ...refreshPaths,
                'app/Filament/**',
                'app/Providers/Filament/**',
            ],
        }),
    ],

    // ── Production build optimizations ─────────────────────────────
    build: {
        // Target modern browsers for smaller bundles
        target: 'es2020',

        // Enable minification with terser for better compression
        minify: 'esbuild',

        // Generate source maps only in dev
        sourcemap: false,

        // Split vendor chunks for better caching
        rollupOptions: {
            output: {
                manualChunks(id) {
                    if (id.includes('node_modules')) {
                        return 'vendor';
                    }
                },
            },
        },

        // Increase chunk size warning limit (Filament is large)
        chunkSizeWarningLimit: 1600,

        // Enable CSS code splitting
        cssCodeSplit: true,
    },

    // Optimize dependency pre-bundling
    optimizeDeps: {
        include: ['axios'],
    },
});
