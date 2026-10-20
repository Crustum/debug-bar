import { defineConfig } from 'vite';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
  base: '/debug_bar/assets/',
  build: {
    outDir: 'webroot/dist',
    lib: {
      entry: 'webroot/js/debugbar.js',
      name: 'DebugBar',
      formats: ['iife'],
      fileName: () => 'debugbar.js',
    },
    rollupOptions: {
      output: {
        assetFileNames: (assetInfo) => {
          if (assetInfo.names?.some((name) => name.endsWith('.css'))) {
            return 'debugbar[extname]';
          }
          return '[name][extname]';
        },
      },
    },
  },
  plugins: [tailwindcss()],
});
