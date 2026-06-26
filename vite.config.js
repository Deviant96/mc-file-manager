import { fileURLToPath, URL } from 'node:url';
import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';

// Builds the Vue SPA into assets/build with a stable filename so the PHP
// admin bootstrap can enqueue it without parsing a manifest.
export default defineConfig({
  // Relative base so emitted asset URLs (fonts, workers, lazy chunks) resolve
  // correctly when the plugin is served from /wp-content/plugins/...
  base: './',
  plugins: [vue()],
  resolve: {
    alias: {
      '@': fileURLToPath(new URL('./src', import.meta.url)),
    },
  },
  build: {
    outDir: 'assets/build',
    emptyOutDir: true,
    cssCodeSplit: false,
    rollupOptions: {
      input: fileURLToPath(new URL('./src/main.js', import.meta.url)),
      output: {
        entryFileNames: 'mcfm-app.js',
        chunkFileNames: 'chunks/[name]-[hash].js',
        assetFileNames: (assetInfo) => {
          if (assetInfo.name && assetInfo.name.endsWith('.css')) {
            return 'mcfm-app.css';
          }
          return 'assets/[name]-[hash][extname]';
        },
      },
    },
  },
  worker: {
    format: 'es',
  },
});
