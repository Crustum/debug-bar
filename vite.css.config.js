import { defineConfig } from 'vite';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
  base: '/crustum/debug_bar/dist/',
  build: {
    outDir: 'webroot/dist',
    emptyOutDir: false,
    rollupOptions: {
      input: {
        styles: 'webroot/js/styles.js',
      },
      output: {
        entryFileNames: '[name].js',
        assetFileNames: (assetInfo) => {
          if (assetInfo.names?.some((name) => name.endsWith('.css'))) {
            return 'debugbar[extname]';
          }
          return 'assets/[name]-[hash][extname]';
        },
      },
    },
  },
  plugins: [tailwindcss()],
});
