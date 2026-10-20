import { defineConfig } from 'vite';

export default defineConfig({
  base: '/crustum/debug_bar/dist/',
  build: {
    outDir: 'webroot/dist',
    emptyOutDir: false,
    rollupOptions: {
      input: {
        shell: 'webroot/js/shell/boot/boot.js',
      },
      output: {
        entryFileNames: '[name].js',
        format: 'es',
      },
    },
  },
});
