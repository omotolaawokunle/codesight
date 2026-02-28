import { fileURLToPath, URL } from 'node:url'

import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import tailwindcss from '@tailwindcss/vite'
import vueDevTools from 'vite-plugin-vue-devtools'

export default defineConfig({
  plugins: [
    tailwindcss(),
    vue(),
    vueDevTools(),
  ],
  resolve: {
    alias: {
      '@': fileURLToPath(new URL('./src', import.meta.url)),
    },
  },
  server: {
    port: 5173,
  },
  build: {
    rollupOptions: {
      output: {
        manualChunks: {
          // Core Vue runtime and router kept separate from app code
          'vendor-vue': ['vue', 'vue-router', 'pinia'],
          // Axios is standalone so it can be cached independently
          'vendor-http': ['axios'],
          // Syntax highlighting is the heaviest dependency — lazy loaded
          'vendor-highlight': ['highlight.js'],
          // Markdown renderer
          'vendor-markdown': ['marked', 'dompurify'],
          // Headless UI and Heroicons
          'vendor-ui': ['@headlessui/vue', '@heroicons/vue'],
        },
      },
    },
  },
})
