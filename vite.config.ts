import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import { resolve } from 'node:path';

/**
 * Vite config tuned for a WordPress plugin build.
 *
 * - Emits a manifest.json so PHP can look up hashed asset filenames.
 * - Writes output to `assets/dist/` (which PHP serves via wp_enqueue_*).
 * - Disables the dev server's HTML entry; WP handles the page shell. We only
 *   ship one JS bundle and one CSS bundle, both versioned by the manifest.
 */
export default defineConfig({
    plugins: [react()],

    root: resolve(__dirname, 'frontend'),

    resolve: {
        alias: {
            '@': resolve(__dirname, 'frontend/src'),
        },
    },

    build: {
        outDir: resolve(__dirname, 'assets/dist'),
        emptyOutDir: true,
        manifest: true,
        sourcemap: true,
        rollupOptions: {
            input: {
                app: resolve(__dirname, 'frontend/src/main.tsx'),
            },
            output: {
                entryFileNames: 'assets/[name]-[hash].js',
                chunkFileNames: 'assets/[name]-[hash].js',
                assetFileNames: 'assets/[name]-[hash][extname]',
            },
        },
    },

    server: {
        port: 5173,
        strictPort: true,
        cors: true,
        hmr: {
            host: 'localhost',
        },
    },

    test: {
        environment: 'jsdom',
        globals: true,
        setupFiles: resolve(__dirname, 'frontend/src/test-setup.ts'),
        include: ['frontend/src/**/*.{test,spec}.{ts,tsx}'],
        coverage: {
            reporter: ['text', 'html', 'clover'],
            include: ['frontend/src/**/*.{ts,tsx}'],
            exclude: [
                'frontend/src/**/*.{test,spec}.{ts,tsx}',
                'frontend/src/test-setup.ts',
                'frontend/src/main.tsx',
            ],
        },
    },
});
